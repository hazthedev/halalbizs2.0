<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Seeds the storefront CMS pages with substantive baseline copy. The legal
 * pages (terms / privacy-PDPA / refund) are a sound starting point but MUST be
 * reviewed by Malaysian counsel before go-live (docs/10 launch checklist).
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $slug => $page) {
            Page::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => ['en' => $page['title_en'], 'ms' => $page['title_ms']],
                    'body' => ['en' => $page['body_en'], 'ms' => $page['body_ms']],
                    'is_active' => true,
                ],
            );
        }
    }

    /** @return array<string, array<string, string>> */
    private function pages(): array
    {
        $brand = config('app.name', 'HalalBizs');

        return [
            'terms' => [
                'title_en' => 'Terms & Conditions',
                'title_ms' => 'Terma & Syarat',
                'body_en' => <<<HTML
                <h2>Terms &amp; Conditions</h2>
                <p>Welcome to {$brand}. By creating an account, browsing, or placing an order you agree to these terms.</p>
                <h3>1. The marketplace</h3>
                <p>{$brand} is an online marketplace that connects independent sellers with buyers. {$brand} is <strong>not the seller of record</strong> for products listed by third-party sellers; the contract of sale is between you and the seller. {$brand} facilitates discovery, payment, and dispute resolution.</p>
                <h3>2. Pricing &amp; tax</h3>
                <p>All prices are shown in Malaysian Ringgit (RM) and are <strong>tax-inclusive</strong> where applicable (SST is calculated and shown at checkout for registered sellers). The price payable is the amount confirmed at checkout.</p>
                <h3>3. Orders &amp; payment</h3>
                <p>An order is a binding offer to purchase. Payment is taken via the supported gateways (FPX, cards, e-wallets) or cash on delivery where available. Stock and vouchers are validated atomically at checkout; an order may be declined if an item sells out.</p>
                <h3>4. Delivery</h3>
                <p>Sellers are responsible for dispatch within their stated handling time. Funds are held in escrow and released to the seller only after delivery is confirmed or the auto-complete window lapses.</p>
                <h3>5. Acceptable use</h3>
                <p>You may not misuse the platform, list prohibited or non-halal-misrepresented goods, manipulate reviews or vouchers, or abuse loyalty, group-buy, or affiliate mechanics. Accounts that do so may be suspended.</p>
                <h3>6. Loyalty, vouchers &amp; promotions</h3>
                <p>Loyalty Coins, vouchers, spin rewards, group-buy deals, and affiliate commissions have no cash value except as expressly stated, may expire, and may be withdrawn or adjusted in cases of error or abuse.</p>
                <h3>7. Liability</h3>
                <p>To the extent permitted by law, {$brand}'s liability is limited to facilitating the transaction. Product quality, descriptions, and fulfilment are the seller's responsibility.</p>
                <h3>8. Changes</h3>
                <p>We may update these terms; material changes will be notified. Continued use constitutes acceptance.</p>
                <p><em>This is baseline copy pending review by qualified Malaysian legal counsel.</em></p>
                HTML,
                'body_ms' => <<<HTML
                <h2>Terma &amp; Syarat</h2>
                <p>Selamat datang ke {$brand}. Dengan membuka akaun, melayari, atau membuat pesanan, anda bersetuju dengan terma ini.</p>
                <h3>1. Pasar dalam talian</h3>
                <p>{$brand} ialah pasar dalam talian yang menghubungkan penjual bebas dengan pembeli. {$brand} <strong>bukan penjual berdaftar</strong> bagi produk yang disenaraikan oleh penjual pihak ketiga; kontrak jualan adalah antara anda dan penjual.</p>
                <h3>2. Harga &amp; cukai</h3>
                <p>Semua harga dipaparkan dalam Ringgit Malaysia (RM) dan <strong>termasuk cukai</strong> jika berkenaan (SST dikira dan dipaparkan semasa pembayaran bagi penjual berdaftar).</p>
                <h3>3. Pesanan &amp; pembayaran</h3>
                <p>Pesanan ialah tawaran mengikat untuk membeli. Pembayaran dibuat melalui gerbang yang disokong (FPX, kad, e-dompet) atau tunai semasa penghantaran jika tersedia.</p>
                <h3>4. Penghantaran</h3>
                <p>Penjual bertanggungjawab menghantar dalam masa pengendalian yang dinyatakan. Dana dipegang dalam escrow dan dilepaskan kepada penjual hanya selepas penghantaran disahkan.</p>
                <h3>5. Penggunaan yang dibenarkan</h3>
                <p>Anda tidak boleh menyalahgunakan platform, menyenaraikan barangan terlarang, memanipulasi ulasan atau baucar, atau menyalahgunakan mekanik kesetiaan, beli berkumpulan, atau afiliat.</p>
                <h3>6. Kesetiaan, baucar &amp; promosi</h3>
                <p>Syiling Kesetiaan, baucar, ganjaran pusingan, tawaran beli berkumpulan, dan komisen afiliat tiada nilai tunai kecuali seperti yang dinyatakan, dan boleh luput atau diselaraskan sekiranya berlaku ralat atau penyalahgunaan.</p>
                <h3>7. Liabiliti</h3>
                <p>Setakat yang dibenarkan undang-undang, liabiliti {$brand} terhad kepada memudahkan transaksi. Kualiti produk dan pemenuhan adalah tanggungjawab penjual.</p>
                <h3>8. Perubahan</h3>
                <p>Kami boleh mengemas kini terma ini; perubahan penting akan dimaklumkan.</p>
                <p><em>Ini ialah kandungan asas menunggu semakan oleh peguam Malaysia yang berkelayakan.</em></p>
                HTML,
            ],
            'privacy' => [
                'title_en' => 'Privacy Policy',
                'title_ms' => 'Dasar Privasi',
                'body_en' => <<<HTML
                <h2>Privacy Policy (PDPA)</h2>
                <p>This policy explains how {$brand} collects, uses, and protects your personal data in line with Malaysia's Personal Data Protection Act 2010 (PDPA).</p>
                <h3>1. Data we collect</h3>
                <p>Account details (name, email, phone), delivery addresses, order and payment metadata (we do not store full card numbers), device/usage data, and content you submit (reviews, messages, support tickets).</p>
                <h3>2. How we use it</h3>
                <p>To process and deliver orders, prevent fraud, provide support, personalise recommendations and search, operate loyalty/affiliate/subscription features, and meet legal/tax obligations (including e-invoicing).</p>
                <h3>3. Sharing</h3>
                <p>We share the minimum necessary with the relevant seller (to fulfil your order), payment and logistics providers, and government authorities where legally required. We do not sell your personal data.</p>
                <h3>4. Retention</h3>
                <p>We keep data only as long as needed for the purposes above or as required by law (e.g. tax/e-invoice records).</p>
                <h3>5. Your rights</h3>
                <p>You may access, correct, or request deletion of your personal data, and withdraw consent for marketing, by contacting support. Some data must be retained to meet legal obligations.</p>
                <h3>6. Security</h3>
                <p>Data is transmitted over TLS and access is restricted. Backups are encrypted and access-controlled.</p>
                <p><em>This is baseline copy pending review by qualified Malaysian legal counsel.</em></p>
                HTML,
                'body_ms' => <<<HTML
                <h2>Dasar Privasi (PDPA)</h2>
                <p>Dasar ini menerangkan cara {$brand} mengumpul, menggunakan, dan melindungi data peribadi anda selaras dengan Akta Perlindungan Data Peribadi 2010 (PDPA).</p>
                <h3>1. Data yang dikumpul</h3>
                <p>Butiran akaun (nama, e-mel, telefon), alamat penghantaran, metadata pesanan dan pembayaran (kami tidak menyimpan nombor kad penuh), data peranti/penggunaan, dan kandungan yang anda hantar.</p>
                <h3>2. Cara kami menggunakannya</h3>
                <p>Untuk memproses dan menghantar pesanan, mencegah penipuan, memberikan sokongan, memperibadikan cadangan dan carian, mengendalikan ciri kesetiaan/afiliat/langganan, dan memenuhi kewajipan undang-undang/cukai (termasuk e-invois).</p>
                <h3>3. Perkongsian</h3>
                <p>Kami berkongsi maklumat minimum yang perlu dengan penjual berkaitan, pembekal pembayaran dan logistik, serta pihak berkuasa jika dikehendaki undang-undang. Kami tidak menjual data peribadi anda.</p>
                <h3>4. Pengekalan</h3>
                <p>Kami menyimpan data hanya selagi diperlukan untuk tujuan di atas atau seperti yang dikehendaki undang-undang.</p>
                <h3>5. Hak anda</h3>
                <p>Anda boleh mengakses, membetulkan, atau memohon pemadaman data peribadi anda, dan menarik balik persetujuan pemasaran, dengan menghubungi sokongan.</p>
                <h3>6. Keselamatan</h3>
                <p>Data dihantar melalui TLS dan akses adalah terhad. Sandaran disulitkan dan dikawal akses.</p>
                <p><em>Ini ialah kandungan asas menunggu semakan oleh peguam Malaysia yang berkelayakan.</em></p>
                HTML,
            ],
            'refund-policy' => [
                'title_en' => 'Refund Policy',
                'title_ms' => 'Dasar Bayaran Balik',
                'body_en' => <<<HTML
                <h2>Refund &amp; Return Policy</h2>
                <h3>1. Eligibility</h3>
                <p>You may request a return for items that are damaged, defective, materially not as described, or wrong. Requests should be raised from your order within the return window shown on the order.</p>
                <h3>2. Process</h3>
                <p>Open the order and submit a return request with photos and a reason. The seller responds; unresolved cases are escalated to {$brand} for a final decision.</p>
                <h3>3. Refunds</h3>
                <p>Approved refunds are returned to your original payment method (or as store credit where agreed). Refunds may be partial for partial returns. Any Loyalty Coins applied to the order are returned proportionally; coins earned on a refunded order may be reversed.</p>
                <h3>4. Non-returnable items</h3>
                <p>Perishables, intimate goods, and made-to-order items may be non-returnable unless faulty.</p>
                <h3>5. Cash on delivery</h3>
                <p>COD refunds are processed as bank transfers or store credit once the return is verified.</p>
                <p><em>This is baseline copy pending review by qualified Malaysian legal counsel.</em></p>
                HTML,
                'body_ms' => <<<HTML
                <h2>Dasar Bayaran Balik &amp; Pemulangan</h2>
                <h3>1. Kelayakan</h3>
                <p>Anda boleh memohon pemulangan untuk item yang rosak, cacat, tidak seperti yang diterangkan, atau salah. Permohonan perlu dibuat dari pesanan anda dalam tempoh pemulangan yang dipaparkan.</p>
                <h3>2. Proses</h3>
                <p>Buka pesanan dan hantar permohonan pemulangan dengan foto dan sebab. Penjual akan membalas; kes yang tidak selesai akan dirujuk kepada {$brand} untuk keputusan muktamad.</p>
                <h3>3. Bayaran balik</h3>
                <p>Bayaran balik yang diluluskan dikembalikan ke kaedah pembayaran asal (atau sebagai kredit kedai jika dipersetujui). Syiling Kesetiaan yang digunakan akan dikembalikan secara berkadar; syiling yang diperoleh atas pesanan yang dipulangkan boleh ditarik balik.</p>
                <h3>4. Item tidak boleh dipulangkan</h3>
                <p>Barangan mudah rosak, barangan peribadi, dan item dibuat atas pesanan mungkin tidak boleh dipulangkan kecuali rosak.</p>
                <h3>5. Tunai semasa penghantaran</h3>
                <p>Bayaran balik COD diproses sebagai pemindahan bank atau kredit kedai selepas pemulangan disahkan.</p>
                <p><em>Ini ialah kandungan asas menunggu semakan oleh peguam Malaysia yang berkelayakan.</em></p>
                HTML,
            ],
            'about' => [
                'title_en' => 'About Us',
                'title_ms' => 'Tentang Kami',
                'body_en' => "<h2>About {$brand}</h2><p>{$brand} is a Malaysian multi-vendor marketplace bringing trusted, halal-friendly sellers and shoppers together — with fair fees, buyer protection, and bilingual support.</p>",
                'body_ms' => "<h2>Tentang {$brand}</h2><p>{$brand} ialah pasar pelbagai penjual Malaysia yang menghubungkan penjual yang dipercayai dan mesra halal dengan pembeli — dengan yuran adil, perlindungan pembeli, dan sokongan dwibahasa.</p>",
            ],
            'faq' => [
                'title_en' => 'FAQ',
                'title_ms' => 'Soalan Lazim',
                'body_en' => '<h2>Frequently Asked Questions</h2><p>Find answers about ordering, payment, delivery, returns, Loyalty Coins, and selling on the platform in our Help Centre.</p>',
                'body_ms' => '<h2>Soalan Lazim</h2><p>Cari jawapan tentang pesanan, pembayaran, penghantaran, pemulangan, Syiling Kesetiaan, dan menjual di platform dalam Pusat Bantuan kami.</p>',
            ],
        ];
    }
}
