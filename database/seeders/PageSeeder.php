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
        $brand = $this->brand();
        $vi = (require database_path('seeders/data/vietnamese-cms.php'))['pages'];

        foreach ($this->pages() as $slug => $page) {
            // firstOrCreate, NOT updateOrCreate. These are CMS pages an admin
            // edits in the panel; updateOrCreate reverted every one of those
            // edits each time the seeder ran, which is precisely why this
            // seeder was kept out of deploy.sh — and that exclusion then meant a
            // NEW page (affiliate-terms, 2026-08-10) 404'd in production while
            // passing every local test.
            //
            // Create-if-missing fixes both ends: new pages land on deploy, and
            // nothing a human wrote is ever overwritten. Baseline copy changes
            // in this file therefore do NOT propagate to an existing page — edit
            // it in the admin panel, which is where it now lives.
            $model = Page::firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => ['en' => $page['title_en'], 'ms' => $page['title_ms'], 'vi' => $vi[$slug]['title']],
                    'body' => [
                        'en' => $page['body_en'],
                        'ms' => $page['body_ms'],
                        'vi' => str_replace(':brand', $brand, $vi[$slug]['body']),
                    ],
                    'is_active' => true,
                ],
            );

            if ($slug === 'about') {
                $this->upgradeShortAboutPage($model, $page, $vi[$slug]);
            }
        }
    }

    /**
     * Upgrade only the original one-paragraph baseline. A later administrator
     * edit is left untouched, including when this seeder runs on every deploy.
     *
     * @param  array<string, string>  $page
     * @param  array{title: string, body: string}  $vi
     */
    private function upgradeShortAboutPage(Page $model, array $page, array $vi): void
    {
        $brand = $this->brand();
        $legacy = [
            'en' => "<h2>About {$brand}</h2><p>{$brand} is a Malaysian multi-vendor marketplace bringing trusted, halal-friendly sellers and shoppers together — with fair fees, buyer protection, and bilingual support.</p>",
            'ms' => "<h2>Tentang {$brand}</h2><p>{$brand} ialah pasar pelbagai penjual Malaysia yang menghubungkan penjual yang dipercayai dan mesra halal dengan pembeli — dengan yuran adil, perlindungan pembeli, dan sokongan dwibahasa.</p>",
            'vi' => "<h2>Về {$brand}</h2><p>{$brand} là sàn giao dịch đa nhà bán hàng tại Malaysia, kết nối người bán đáng tin cậy, thân thiện với halal cùng người mua — với mức phí công bằng, chính sách bảo vệ người mua và hỗ trợ đa ngôn ngữ.</p>",
        ];
        $replacement = [
            'en' => $page['body_en'],
            'ms' => $page['body_ms'],
            'vi' => str_replace(':brand', $brand, $vi['body']),
        ];
        $changed = false;

        foreach ($replacement as $locale => $body) {
            $current = trim((string) $model->getTranslation('body', $locale, false));

            if ($current === '' || $current === $legacy[$locale]) {
                $model->setTranslation('body', $locale, $body);
                $changed = true;
            }
        }

        if ($changed) {
            $model->save();
        }
    }

    /** @return array<string, array<string, string>> */
    private function pages(): array
    {
        $brand = $this->brand();

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
                'body_en' => <<<HTML
                <h2>About {$brand}</h2>
                <p>{$brand} is a Malaysian multi-vendor marketplace built to make everyday commerce more transparent, inclusive, and useful for buyers and independent sellers. We bring halal-conscious shopping, clear product information, dependable fulfilment, and practical seller tools into one marketplace.</p>
                <h3>Our purpose</h3>
                <p>We want buyers to understand what they are purchasing and who they are purchasing it from. At the same time, we want Malaysian businesses—from growing homegrown brands to established distributors—to reach customers without needing to build their own marketplace infrastructure.</p>
                <h3>How the marketplace works</h3>
                <p>Independent sellers operate their own stores, publish their products, manage stock, fulfil orders, and remain responsible for the accuracy and quality of their listings. {$brand} provides discovery, checkout, payment coordination, order records, buyer support, returns and dispute handling, and tools that help sellers manage their business.</p>
                <h3>Our approach to halal confidence</h3>
                <p>We treat halal evidence at product level. A verified seller does not automatically make every item halal-certified. Where certification is claimed, buyers can inspect the certificate linked to that product and search the public certificate register. This keeps the claim attached to the item and scope it actually covers.</p>
                <h3>For buyers</h3>
                <p>Buyers can shop across multiple sellers in one checkout, choose supported payment methods, follow each parcel separately, review product and certificate details, and request help when an order does not arrive as expected. The storefront is available in English, Bahasa Melayu, and Vietnamese, with prices displayed in supported currencies.</p>
                <h3>For sellers</h3>
                <p>Sellers receive a dedicated centre for products, variants, inventory, orders, promotions, withdrawals, certificates, and localized content. Our role is to make the operating tools clear and fair, while sellers focus on products, service, and lasting customer relationships.</p>
                <h3>What guides us</h3>
                <ul>
                  <li><strong>Clarity</strong> — important claims should be visible and understandable.</li>
                  <li><strong>Traceability</strong> — product, order, payment, and certificate records should connect.</li>
                  <li><strong>Fair participation</strong> — buyers and sellers should know the rules before they act.</li>
                  <li><strong>Respect for choice</strong> — halal-conscious shoppers need evidence, not assumptions.</li>
                </ul>
                <p>{$brand} is a marketplace operator, not a halal certification body and not the seller of record for third-party listings. Certification decisions remain with the recognised issuing body, while product quality and fulfilment remain the seller's responsibility.</p>
                HTML,
                'body_ms' => <<<HTML
                <h2>Tentang {$brand}</h2>
                <p>{$brand} ialah pasar berbilang penjual Malaysia yang dibina untuk menjadikan urusan jual beli harian lebih telus, inklusif dan berguna kepada pembeli serta penjual bebas. Kami menghimpunkan pembelian yang peka halal, maklumat produk yang jelas, pemenuhan yang boleh dipercayai dan alat penjual yang praktikal dalam satu pasar.</p>
                <h3>Tujuan kami</h3>
                <p>Kami mahu pembeli memahami apa yang dibeli dan daripada siapa mereka membelinya. Pada masa yang sama, kami mahu perniagaan Malaysia—daripada jenama tempatan yang sedang berkembang hingga pengedar yang mantap—mencapai pelanggan tanpa perlu membina infrastruktur pasar mereka sendiri.</p>
                <h3>Cara pasar ini berfungsi</h3>
                <p>Penjual bebas mengendalikan kedai sendiri, menerbitkan produk, mengurus stok, memenuhi pesanan, serta kekal bertanggungjawab atas ketepatan dan kualiti penyenaraian. {$brand} menyediakan penemuan produk, pembayaran, penyelarasan bayaran, rekod pesanan, sokongan pembeli, pemulangan dan pengendalian pertikaian, serta alat untuk membantu penjual mengurus perniagaan.</p>
                <h3>Pendekatan kami terhadap keyakinan halal</h3>
                <p>Kami mengendalikan bukti halal pada peringkat produk. Penjual yang disahkan tidak bermakna setiap produknya diperakui halal secara automatik. Apabila pensijilan dituntut, pembeli boleh memeriksa sijil yang dipautkan kepada produk tersebut dan membuat carian dalam daftar sijil awam. Dengan itu, tuntutan kekal terikat pada item dan skop sebenar yang dilindungi.</p>
                <h3>Untuk pembeli</h3>
                <p>Pembeli boleh membeli daripada beberapa penjual dalam satu pembayaran, memilih kaedah bayaran yang disokong, menjejaki setiap bungkusan secara berasingan, menyemak butiran produk dan sijil, serta mendapatkan bantuan apabila pesanan tidak diterima seperti dijangka. Kedai tersedia dalam bahasa Inggeris, Bahasa Melayu dan Vietnam, dengan harga dipaparkan dalam mata wang yang disokong.</p>
                <h3>Untuk penjual</h3>
                <p>Penjual menerima pusat khusus untuk produk, variasi, inventori, pesanan, promosi, pengeluaran wang, sijil dan kandungan berbilang bahasa. Peranan kami adalah menjadikan alat operasi jelas dan adil, sementara penjual memberi tumpuan kepada produk, perkhidmatan dan hubungan pelanggan yang berkekalan.</p>
                <h3>Prinsip kami</h3>
                <ul>
                  <li><strong>Kejelasan</strong> — tuntutan penting perlu kelihatan dan mudah difahami.</li>
                  <li><strong>Kebolehkesanan</strong> — rekod produk, pesanan, bayaran dan sijil perlu saling berkait.</li>
                  <li><strong>Penyertaan yang adil</strong> — pembeli dan penjual perlu mengetahui peraturan sebelum bertindak.</li>
                  <li><strong>Menghormati pilihan</strong> — pembeli yang peka halal memerlukan bukti, bukan andaian.</li>
                </ul>
                <p>{$brand} ialah pengendali pasar, bukan badan pensijilan halal dan bukan penjual berdaftar bagi penyenaraian pihak ketiga. Keputusan pensijilan kekal di bawah badan pengeluar yang diiktiraf, manakala kualiti produk dan pemenuhan kekal sebagai tanggungjawab penjual.</p>
                HTML,
            ],
            'trust-safety' => [
                'title_en' => 'Trust & Safety',
                'title_ms' => 'Kepercayaan & Keselamatan',
                'body_en' => <<<HTML
                <h2>Trust &amp; Safety at {$brand}</h2>
                <p>Trust is built from evidence, clear responsibilities, and a record of what happened—not from a badge alone. {$brand} combines seller review, product-level halal information, protected order flows, account safeguards, and human support so buyers and sellers can make informed decisions.</p>
                <h3>Seller review</h3>
                <p>Sellers apply with business and contact information before their store is approved. Approval allows a seller to operate on the marketplace; it does not certify every product they list. Sellers remain responsible for accurate descriptions, lawful goods, genuine documents, stock, packing, and delivery.</p>
                <h3>Product-level halal evidence</h3>
                <p>Halal claims are attached to individual products and the certificate records that cover them. A product may show the issuing body, certificate number, validity period, batch details, and scope. Buyers can use the certificate register to check the current record. If evidence expires, is rejected, or no longer covers the item, affected products can be held from publication or removed from sale.</p>
                <h3>Buyer protection and order records</h3>
                <p>Checkout records the seller, item, price, discount, payment and delivery details for each part of an order. Funds follow the order lifecycle and are released according to delivery and completion rules. Buyers can track parcels, raise a return request with evidence, and escalate unresolved cases for marketplace review.</p>
                <h3>Payments and account security</h3>
                <p>Payments use supported rails such as FPX, cards, e-wallets, or cash on delivery where available. Full card numbers are not stored by {$brand}. Rate limits, email verification, optional two-factor authentication, trusted-device controls, and new-device alerts help protect accounts. Never share your password, OTP, bank PIN, or card security code through chat, email, or telephone.</p>
                <h3>Honest listings and reviews</h3>
                <p>Sellers must not misrepresent ingredients, origin, certification, condition, price, or availability. Reviews should reflect a genuine order experience. Manipulated reviews, fake certificates, prohibited goods, voucher abuse, and attempts to move payment outside the marketplace may lead to listing removal, account restriction, or investigation.</p>
                <h3>How to shop with confidence</h3>
                <ol>
                  <li>Read the complete product description, variation, quantity, ingredients, and seller information.</li>
                  <li>For halal-certified items, open the certificate details and confirm the issuing body, validity, and covered product.</li>
                  <li>Keep payment and messages inside {$brand} so the transaction record can support you.</li>
                  <li>Inspect the parcel promptly and raise any issue from the order page within the displayed return window.</li>
                  <li>Contact the Help Centre when a seller response does not resolve the issue.</li>
                </ol>
                <h3>Report a concern</h3>
                <p>If you see a suspicious listing, misleading halal claim, unsafe product, unusual payment request, or account activity you do not recognise, stop the transaction and contact the Help Centre. Include the product or order reference and any supporting screenshots so the team can investigate accurately.</p>
                <p>No marketplace can remove every risk. Our commitment is to make claims checkable, preserve transaction evidence, apply the published rules consistently, and provide a clear route to help when something goes wrong.</p>
                HTML,
                'body_ms' => <<<HTML
                <h2>Kepercayaan &amp; Keselamatan di {$brand}</h2>
                <p>Kepercayaan dibina melalui bukti, tanggungjawab yang jelas dan rekod tentang apa yang berlaku—bukan melalui lencana semata-mata. {$brand} menggabungkan semakan penjual, maklumat halal pada peringkat produk, aliran pesanan yang dilindungi, perlindungan akaun dan sokongan manusia supaya pembeli serta penjual boleh membuat keputusan berdasarkan maklumat.</p>
                <h3>Semakan penjual</h3>
                <p>Penjual memohon dengan maklumat perniagaan dan perhubungan sebelum kedai diluluskan. Kelulusan membolehkan penjual beroperasi di pasar; ia tidak memperakui setiap produk yang disenaraikan. Penjual kekal bertanggungjawab atas penerangan yang tepat, barangan yang sah, dokumen tulen, stok, pembungkusan dan penghantaran.</p>
                <h3>Bukti halal pada peringkat produk</h3>
                <p>Tuntutan halal dipautkan kepada produk individu dan rekod sijil yang melindunginya. Produk boleh memaparkan badan pengeluar, nombor sijil, tempoh sah, butiran kelompok dan skop. Pembeli boleh menggunakan daftar sijil untuk menyemak rekod semasa. Jika bukti tamat tempoh, ditolak atau tidak lagi melindungi item, produk terjejas boleh ditahan daripada penerbitan atau dikeluarkan daripada jualan.</p>
                <h3>Perlindungan pembeli dan rekod pesanan</h3>
                <p>Pembayaran merekodkan penjual, item, harga, diskaun, bayaran dan butiran penghantaran bagi setiap bahagian pesanan. Dana mengikut kitaran hayat pesanan dan dilepaskan menurut peraturan penghantaran dan penyelesaian. Pembeli boleh menjejaki bungkusan, mengemukakan permohonan pemulangan bersama bukti dan merujuk kes yang belum selesai untuk semakan pasar.</p>
                <h3>Bayaran dan keselamatan akaun</h3>
                <p>Bayaran menggunakan saluran yang disokong seperti FPX, kad, e-dompet atau tunai semasa penghantaran jika tersedia. Nombor kad penuh tidak disimpan oleh {$brand}. Had kadar, pengesahan e-mel, pengesahan dua faktor pilihan, kawalan peranti dipercayai dan amaran peranti baharu membantu melindungi akaun. Jangan sekali-kali berkongsi kata laluan, OTP, PIN bank atau kod keselamatan kad melalui sembang, e-mel atau telefon.</p>
                <h3>Penyenaraian dan ulasan yang jujur</h3>
                <p>Penjual tidak boleh memberikan gambaran palsu tentang ramuan, asal, pensijilan, keadaan, harga atau ketersediaan. Ulasan perlu mencerminkan pengalaman pesanan sebenar. Ulasan yang dimanipulasi, sijil palsu, barangan terlarang, penyalahgunaan baucar dan cubaan memindahkan bayaran ke luar pasar boleh menyebabkan penyenaraian dibuang, akaun dihadkan atau siasatan dijalankan.</p>
                <h3>Cara membeli dengan yakin</h3>
                <ol>
                  <li>Baca penerangan produk, variasi, kuantiti, ramuan dan maklumat penjual dengan lengkap.</li>
                  <li>Bagi item yang diperakui halal, buka butiran sijil dan sahkan badan pengeluar, tempoh sah serta produk yang dilindungi.</li>
                  <li>Kekalkan bayaran dan mesej di dalam {$brand} supaya rekod transaksi boleh menyokong anda.</li>
                  <li>Periksa bungkusan dengan segera dan laporkan isu dari halaman pesanan dalam tempoh pemulangan yang dipaparkan.</li>
                  <li>Hubungi Pusat Bantuan apabila jawapan penjual tidak menyelesaikan isu.</li>
                </ol>
                <h3>Laporkan kebimbangan</h3>
                <p>Jika anda melihat penyenaraian mencurigakan, tuntutan halal yang mengelirukan, produk tidak selamat, permintaan bayaran luar biasa atau aktiviti akaun yang tidak dikenali, hentikan transaksi dan hubungi Pusat Bantuan. Sertakan rujukan produk atau pesanan serta tangkap layar sokongan supaya pasukan boleh menyiasat dengan tepat.</p>
                <p>Tiada pasar yang boleh menghapuskan semua risiko. Komitmen kami adalah menjadikan tuntutan boleh diperiksa, memelihara bukti transaksi, menggunakan peraturan yang diterbitkan secara konsisten dan menyediakan laluan bantuan yang jelas apabila sesuatu berlaku.</p>
                HTML,
            ],
            'faq' => [
                'title_en' => 'FAQ',
                'title_ms' => 'Soalan Lazim',
                'body_en' => '<h2>Frequently Asked Questions</h2><p>Find answers about ordering, payment, delivery, returns, Loyalty Coins, and selling on the platform in our Help Centre.</p>',
                'body_ms' => '<h2>Soalan Lazim</h2><p>Cari jawapan tentang pesanan, pembayaran, penghantaran, pemulangan, Syiling Kesetiaan, dan menjual di platform dalam Pusat Bantuan kami.</p>',
            ],

            // ⚠ EVERY CLAUSE BELOW DESCRIBES CODE THAT ALREADY RUNS. It is a
            // record of shipped behaviour, not new policy — written after the
            // commission hold and carry-forward shipped (PRs #97, #101) so that
            // creators have something binding to point at. If any of these
            // numbers change, they change in config first and here second:
            //
            //   5%              config('affiliate.commission_rate_bp') = 500
            //   30-day cookie   config('affiliate.cookie_days')
            //   14-day hold     OrderSettings::return_window_days (7)
            //                   + config('affiliate.lock_buffer_days') (7)
            //   RM50 minimum    config('affiliate.min_payout_sen') = 5000
            //
            // The hold is stated as "about two weeks" rather than a hard number
            // because the return window is an admin setting; the precise unlock
            // date is shown per-sale on the creator's own dashboard, which is
            // the figure that is always true.
            'affiliate-terms' => [
                'title_en' => 'Creator Programme Terms',
                'title_ms' => 'Terma Program Kreator',
                'body_en' => <<<HTML
                <h2>Creator Programme Terms</h2>
                <p>These terms cover how commission is earned, held and paid on {$brand}. They describe exactly how the system behaves — nothing here is applied by hand.</p>

                <h3>1. Joining</h3>
                <p>Any account holder can enrol and receive a unique share link. We may suspend a creator account at any time; suspension stops new commission being earned but does not remove commission already earned.</p>

                <h3>2. How a sale is credited to you</h3>
                <p>When someone opens your link we store a referral cookie for 30 days. If they order within that period, the sale is credited to you. Last link wins: if the shopper later clicks another creator's link, the later one is credited. Credit is fixed when the order is placed and does not change afterwards.</p>

                <p><strong>You cannot earn commission on your own purchases.</strong> An order placed by the same account that owns the share link earns nothing.</p>

                <h3>3. What you earn</h3>
                <p>5% of the value of the items in a referred order. Shipping, tax and any platform discounts are excluded. Commission is calculated per shop: an order split across two shops produces one commission per shop, and each follows its own delivery and return timeline.</p>

                <p>Commission is earned when an order is completed, not when it is placed.</p>

                <h3>4. The holding period</h3>
                <p>Newly earned commission is <strong>Pending</strong>, not yet available to withdraw. It becomes available roughly two weeks after delivery — once the buyer's return window has closed, plus a short buffer for late disputes.</p>

                <p>Your dashboard always shows the exact unlock date for each sale. Pending and available amounts are listed separately so you can see what is genuinely yours.</p>

                <h3>5. Returns and refunds</h3>
                <p>Commission follows the sale. If a referred order is refunded, the matching commission is removed.</p>

                <ul>
                  <li><strong>Refunded while pending</strong> — the commission is reduced or removed before it ever became available. Nothing is taken from you.</li>
                  <li><strong>Partly refunded</strong> — the commission is reduced in proportion. A buyer returning part of an order does not cost you the whole commission.</li>
                  <li><strong>Refunded after it became available</strong> — the commission is reversed. If you had already withdrawn it, the shortfall is carried on your balance and covered by your next commissions.</li>
                </ul>

                <p><strong>We will never invoice you or ask you to send money back.</strong> A negative balance simply means your next earnings clear it first. If you never refer again, nothing is pursued.</p>

                <h3>6. Withdrawals</h3>
                <p>You can request a withdrawal once your available balance reaches RM50. One request at a time; we pay to the bank details you supply with the request. A rejected request returns the amount to your available balance.</p>

                <h3>7. Changes</h3>
                <p>We may change the commission rate, the holding period or the minimum withdrawal. Changes apply to sales referred after the change, never retrospectively to commission already earned.</p>

                <p><em>These terms are a plain-language description of how the programme currently operates and should be reviewed by Malaysian counsel before public launch.</em></p>
                HTML,
                'body_ms' => <<<HTML
                <h2>Terma Program Kreator</h2>
                <p>Terma ini menerangkan cara komisen diperoleh, ditahan dan dibayar di {$brand}. Ia menerangkan dengan tepat cara sistem berfungsi — tiada apa-apa di sini yang dikendalikan secara manual.</p>

                <h3>1. Menyertai</h3>
                <p>Mana-mana pemegang akaun boleh mendaftar dan menerima pautan kongsi yang unik. Kami boleh menggantung akaun kreator pada bila-bila masa; penggantungan menghentikan komisen baharu tetapi tidak menghapuskan komisen yang telah diperoleh.</p>

                <h3>2. Bagaimana jualan dikreditkan kepada anda</h3>
                <p>Apabila seseorang membuka pautan anda, kami menyimpan kuki rujukan selama 30 hari. Jika mereka membuat pesanan dalam tempoh itu, jualan dikreditkan kepada anda. Pautan terakhir menang: jika pembeli kemudian mengklik pautan kreator lain, pautan yang lebih lewat itu yang dikreditkan. Kredit ditetapkan semasa pesanan dibuat dan tidak berubah selepas itu.</p>

                <p><strong>Anda tidak boleh memperoleh komisen atas pembelian anda sendiri.</strong> Pesanan yang dibuat oleh akaun yang sama dengan pemilik pautan tidak memperoleh apa-apa.</p>

                <h3>3. Apa yang anda peroleh</h3>
                <p>5% daripada nilai barang dalam pesanan yang dirujuk. Penghantaran, cukai dan sebarang diskaun platform dikecualikan. Komisen dikira mengikut kedai: pesanan yang merangkumi dua kedai menghasilkan satu komisen bagi setiap kedai, dan setiap satu mengikut jadual penghantaran dan pemulangannya sendiri.</p>

                <p>Komisen diperoleh apabila pesanan selesai, bukan semasa ia dibuat.</p>

                <h3>4. Tempoh tahanan</h3>
                <p>Komisen yang baharu diperoleh berstatus <strong>Belum Tersedia</strong> dan belum boleh dikeluarkan. Ia menjadi tersedia kira-kira dua minggu selepas penghantaran — setelah tempoh pemulangan pembeli ditutup, ditambah tempoh penampan yang singkat untuk pertikaian lewat.</p>

                <p>Papan pemuka anda sentiasa menunjukkan tarikh tepat setiap jualan menjadi tersedia. Jumlah belum tersedia dan jumlah tersedia disenaraikan secara berasingan.</p>

                <h3>5. Pemulangan dan bayaran balik</h3>
                <p>Komisen mengikut jualan. Jika pesanan yang dirujuk dibayar balik, komisen yang berkaitan dikeluarkan.</p>

                <ul>
                  <li><strong>Dibayar balik semasa belum tersedia</strong> — komisen dikurangkan atau dikeluarkan sebelum ia menjadi tersedia. Tiada apa-apa diambil daripada anda.</li>
                  <li><strong>Dibayar balik sebahagian</strong> — komisen dikurangkan secara berkadar. Pembeli yang memulangkan sebahagian pesanan tidak menyebabkan anda kehilangan keseluruhan komisen.</li>
                  <li><strong>Dibayar balik selepas ia tersedia</strong> — komisen diterbalikkan. Jika anda telah mengeluarkannya, kekurangan itu dibawa dalam baki anda dan ditampung oleh komisen anda yang seterusnya.</li>
                </ul>

                <p><strong>Kami tidak akan sesekali menghantar invois atau meminta anda memulangkan wang.</strong> Baki negatif bermakna pendapatan anda yang seterusnya akan menjelaskannya dahulu. Jika anda tidak pernah merujuk lagi, tiada tindakan diambil.</p>

                <h3>6. Pengeluaran</h3>
                <p>Anda boleh memohon pengeluaran apabila baki tersedia anda mencapai RM50. Satu permohonan pada satu masa; kami membayar ke butiran bank yang anda berikan bersama permohonan. Permohonan yang ditolak akan mengembalikan jumlah itu ke baki tersedia anda.</p>

                <h3>7. Perubahan</h3>
                <p>Kami boleh mengubah kadar komisen, tempoh tahanan atau jumlah pengeluaran minimum. Perubahan terpakai bagi jualan yang dirujuk selepas perubahan itu, dan tidak sesekali dikenakan ke atas komisen yang telah diperoleh.</p>

                <p><em>Terma ini ialah penerangan bahasa mudah tentang cara program ini beroperasi pada masa ini dan perlu disemak oleh peguam Malaysia sebelum pelancaran umum.</em></p>
                HTML,
            ],
        ];
    }

    private function brand(): string
    {
        $brand = trim((string) config('app.name'));

        return $brand === '' || $brand === 'Laravel' ? 'HalalBizs' : $brand;
    }
}
