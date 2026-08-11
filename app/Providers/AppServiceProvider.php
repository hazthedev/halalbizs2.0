<?php

namespace App\Providers;

use App\Events\OrderPaid;
use App\Events\ProductRestocked;
use App\Events\SubOrderStatusChanged;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureSeller;
use App\Listeners\DispatchOrderWebhooks;
use App\Listeners\NotifyBackInStockSubscribers;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Product;
use App\Models\ProductMetafield;
use App\Models\ReturnRequest;
use App\Models\Store;
use App\Observers\AdminAlertObserver;
use App\Observers\AffiliateAttributionObserver;
use App\Observers\ProductEmbeddingObserver;
use App\Observers\ProductMetafieldObserver;
use App\Observers\SlugRedirectObserver;
use App\Services\EInvoice\EInvoiceProvider;
use App\Services\EInvoice\MyInvoisProvider;
use App\Services\EInvoice\NullProvider;
use App\Services\Search\EmbeddingProvider;
use App\Services\Search\LocalHashEmbedder;
use App\Services\Search\RemoteEmbedder;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use App\Services\Sms\WhatsAppSender;
use App\Support\Money;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Phone-verification delivery. WhatsApp (Cloud API, free tier) once its
        // token + phone-number id are configured; the log stub otherwise, so a
        // preview/dev without Meta credentials still works. Same graceful
        // cutover shape as mail (log→SMTP) and iPay88 (mock→live).
        $this->app->bind(SmsSender::class, function ($app) {
            $wa = config('services.whatsapp');

            $driver = filled($wa['token']) && filled($wa['phone_number_id'])
                ? WhatsAppSender::class
                : LogSmsSender::class;

            return $app->make($driver);
        });

        // E-invoicing provider, selected by config. Defaults to the no-op
        // NullProvider until LHDN MyInvois credentials + cert are supplied.
        $this->app->bind(EInvoiceProvider::class, function () {
            return match (config('einvoice.provider', 'null')) {
                'myinvois' => new MyInvoisProvider((array) config('einvoice.myinvois')),
                default => new NullProvider,
            };
        });

        // Text embeddings (M2.3): a real model in prod, deterministic local
        // embedder for dev/tests. The remote driver self-falls-back when unkeyed.
        $this->app->bind(EmbeddingProvider::class, function () {
            return config('search.driver') === 'remote'
                ? $this->app->make(RemoteEmbedder::class)
                : $this->app->make(LocalHashEmbedder::class);
        });
    }

    public function boot(): void
    {
        // Production hardening (docs/10): force HTTPS URL generation behind the
        // TLS-terminating proxy. Local/dev/tests keep their scheme untouched.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // A mass-assigned key missing from $fillable is silently dropped by
        // default — the failure mode that makes shrinking $fillable dangerous
        // (the write "succeeds" and the column never changes). Outside
        // production a discarded key throws instead, so the suite catches any
        // writer the L3 fillable trim missed. Production stays lenient: better
        // a stale column than a 500 on a path no test covered.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Public API rate limit (docs/10): 60 req/min per IP. Auth login already
        // self-throttles in the Login component (5 attempts).
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // Register and ResetPassword both validate with Password::defaults(), but
        // nothing ever CONFIGURED the defaults — so the whole marketplace's rule
        // was `min(8)` and the reset form accepted `12345678` (found 2026-08-10
        // on the live preview's superadmin). Length alone stops nothing: the
        // common-password lists are all 8+ characters.
        //
        // uncompromised() is a k-anonymity check — only the first 5 characters of
        // the SHA-1 leave the server, never the password — and it FAILS OPEN if
        // the API is unreachable, so it can never lock a customer out.
        //
        // ponytail: uses the framework's default 30s HTTP timeout. Outbound HTTPS
        // is known good on this host (iPay88, EasyParcel, WhatsApp all call out),
        // so a hang is unlikely; if registration ever stalls, bind
        // UncompromisedVerifier to a NotPwnedVerifier with a short timeout.
        Password::defaults(fn () => Password::min(8)->uncompromised());

        // Superadmin bypass (bug #1). The `admin` role carries NO permissions —
        // it only gets you past EnsureAdmin — so every admin section is gated on
        // its own per-person grant. A superadmin sits above that and passes any
        // check, which is what keeps Staff itself reachable after the role was
        // emptied. Returning null (not false) for everyone else is deliberate:
        // it means "no opinion", so the normal permission resolution continues.
        Gate::before(fn ($user) => $user->is_superadmin ? true : null);

        // Section guards must survive the Livewire round trip. /livewire/update
        // re-applies only the middleware on Livewire's persistent list —
        // Authenticate and Authorize (`can:`) — so a component whose route is
        // guarded ONLY by EnsureAdmin/EnsureSeller (the admin dashboard and
        // notifications) stayed drivable from a leaked or stale snapshot after
        // the role was revoked. Both guards re-run per update from here.
        Livewire::addPersistentMiddleware([
            EnsureAdmin::class,
            EnsureSeller::class,
        ]);

        // /up readiness probe (docs/10): the built-in health route dispatches
        // DiagnosingHealth — fail it if the database is unreachable.
        Event::listen(DiagnosingHealth::class, fn () => DB::connection()->getPdo());

        // Outbound order webhooks (M1.7) — explicit single registration.
        Event::listen(OrderPaid::class, [DispatchOrderWebhooks::class, 'onOrderPaid']);
        Event::listen(ProductRestocked::class, NotifyBackInStockSubscribers::class);
        Event::listen(SubOrderStatusChanged::class, [DispatchOrderWebhooks::class, 'onSubOrderStatusChanged']);

        // Affiliate last-click attribution snapshot at order creation (M2.5).
        Order::observe(AffiliateAttributionObserver::class);

        // Search embeddings stay fresh on product + metafield changes (M2.3).
        Product::observe(ProductEmbeddingObserver::class);
        ProductMetafield::observe(ProductMetafieldObserver::class);

        // Slug changes leave a 301 behind (docs/09 §F).
        Product::observe(SlugRedirectObserver::class);
        Store::observe(SlugRedirectObserver::class);
        Category::observe(SlugRedirectObserver::class);

        // Admin bell alerts (database only): pending stores, payout
        // requests, escalated/disputed returns, iPay88 signature mismatches.
        Store::observe(AdminAlertObserver::class);
        Payout::observe(AdminAlertObserver::class);
        ReturnRequest::observe(AdminAlertObserver::class);
        Payment::observe(AdminAlertObserver::class);

        // Plain MYR amount: @money($sen)
        Blade::directive('money', function (string $expression) {
            return "<?php echo \App\Support\Money::format($expression); ?>";
        });

        // Display-currency amount (≈ converted when non-MYR): @price($sen)
        Blade::directive('price', function (string $expression) {
            return "<?php echo app(\App\Services\CurrencyConverter::class)->display($expression); ?>";
        });
    }
}
