<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use Illuminate\Database\Seeder;

/**
 * Starter help-centre content (EN + MS) — realistic marketplace topics.
 * Keyed on category+position so re-seeding updates instead of duplicating.
 */
class HelpArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            // ── Buying ──────────────────────────────────────────────────
            [
                'category' => 'buying', 'position' => 0,
                'title' => ['en' => 'How do I place an order?', 'ms' => 'Bagaimana cara membuat pesanan?'],
                'body' => [
                    'en' => '<p>Find a product, pick a variation if there is one, and tap <strong>Buy now</strong> or <strong>Add to cart</strong>.</p><ul><li>Items from different sellers ship separately but check out together.</li><li>Choose your delivery address and payment method on the checkout page.</li><li>After you place the order, track it under <strong>My orders</strong>.</li></ul>',
                    'ms' => '<p>Cari produk, pilih variasi jika ada, dan tekan <strong>Beli sekarang</strong> atau <strong>Tambah ke troli</strong>.</p><ul><li>Barangan daripada penjual berbeza dihantar berasingan tetapi dibayar sekali.</li><li>Pilih alamat penghantaran dan kaedah pembayaran di halaman pembayaran.</li><li>Selepas pesanan dibuat, jejaki di <strong>Pesanan saya</strong>.</li></ul>',
                ],
            ],
            [
                'category' => 'buying', 'position' => 1,
                'title' => ['en' => 'Can I cancel my order?', 'ms' => 'Bolehkah saya membatalkan pesanan?'],
                'body' => [
                    'en' => '<p>You can cancel from <strong>My orders</strong> while the seller has not shipped it yet.</p><p>Once a parcel is shipped, wait for it to arrive and request a return instead. Unpaid iPay88 orders expire automatically after 60 minutes.</p>',
                    'ms' => '<p>Anda boleh membatalkan dari <strong>Pesanan saya</strong> selagi penjual belum menghantarnya.</p><p>Selepas bungkusan dihantar, tunggu ia tiba dan mohon pemulangan. Pesanan iPay88 yang tidak dibayar luput secara automatik selepas 60 minit.</p>',
                ],
            ],
            // ── Payments ────────────────────────────────────────────────
            [
                'category' => 'payments', 'position' => 0,
                'title' => ['en' => 'Cash on delivery (COD)', 'ms' => 'Tunai semasa penghantaran (COD)'],
                'body' => [
                    'en' => '<p>Pay the courier in cash when your parcel arrives. COD is available on orders up to <strong>RM500</strong> where the seller offers it.</p><p>Have the exact amount ready — couriers may not carry change.</p>',
                    'ms' => '<p>Bayar tunai kepada kurier apabila bungkusan tiba. COD tersedia untuk pesanan sehingga <strong>RM500</strong> jika penjual menawarkannya.</p><p>Sediakan jumlah yang tepat — kurier mungkin tidak membawa wang baki.</p>',
                ],
            ],
            [
                'category' => 'payments', 'position' => 1,
                'title' => ['en' => 'Paying with iPay88 (FPX, cards, e-wallets)', 'ms' => 'Membayar dengan iPay88 (FPX, kad, e-dompet)'],
                'body' => [
                    'en' => '<p>iPay88 handles online banking (FPX), credit and debit cards, and popular e-wallets.</p><ul><li>You are redirected to a secure iPay88 page to pay.</li><li>Your order is confirmed once the bank notifies us — usually within a minute.</li><li>If payment fails, nothing is charged and you can retry from <strong>My orders</strong>.</li></ul>',
                    'ms' => '<p>iPay88 mengendalikan perbankan dalam talian (FPX), kad kredit dan debit, serta e-dompet popular.</p><ul><li>Anda akan dibawa ke halaman iPay88 yang selamat untuk membayar.</li><li>Pesanan disahkan sebaik sahaja bank memaklumkan kami — biasanya dalam seminit.</li><li>Jika pembayaran gagal, tiada caj dikenakan dan anda boleh cuba semula dari <strong>Pesanan saya</strong>.</li></ul>',
                ],
            ],
            [
                'category' => 'payments', 'position' => 2,
                'title' => ['en' => 'Using vouchers at checkout', 'ms' => 'Menggunakan baucar semasa pembayaran'],
                'body' => [
                    'en' => '<p>Enter a voucher code on the checkout page and tap <strong>Apply</strong>.</p><ul><li>Platform vouchers apply to your whole order; store vouchers apply to that seller\'s items only.</li><li>Each voucher shows its minimum spend — for example "RM5 off RM50".</li><li>Vouchers are limited per user and first-come, first-served.</li></ul>',
                    'ms' => '<p>Masukkan kod baucar di halaman pembayaran dan tekan <strong>Guna</strong>.</p><ul><li>Baucar platform terpakai untuk keseluruhan pesanan; baucar kedai hanya untuk barangan penjual itu.</li><li>Setiap baucar menunjukkan perbelanjaan minimum — contohnya "RM5 potongan untuk RM50".</li><li>Baucar terhad bagi setiap pengguna dan siapa cepat dia dapat.</li></ul>',
                ],
            ],
            // ── Shipping ────────────────────────────────────────────────
            [
                'category' => 'shipping', 'position' => 0,
                'title' => ['en' => 'Tracking your shipment', 'ms' => 'Menjejak penghantaran anda'],
                'body' => [
                    'en' => '<p>Open <strong>My orders</strong> and select the order — the timeline shows every step from packing to delivery.</p><p>Once the seller ships, you\'ll see the courier name and tracking number. We\'ll also notify you at each status change.</p>',
                    'ms' => '<p>Buka <strong>Pesanan saya</strong> dan pilih pesanan — garis masa menunjukkan setiap langkah dari pembungkusan hingga penghantaran.</p><p>Selepas penjual menghantar, anda akan melihat nama kurier dan nombor penjejakan. Kami juga akan memaklumkan anda pada setiap perubahan status.</p>',
                ],
            ],
            // ── Returns ─────────────────────────────────────────────────
            [
                'category' => 'returns', 'position' => 0,
                'title' => ['en' => 'Returns and the 7-day window', 'ms' => 'Pemulangan dan tempoh 7 hari'],
                'body' => [
                    'en' => '<p>You can request a return within <strong>7 days</strong> of delivery for damaged, wrong, or not-as-described items.</p><ul><li>Go to the order, choose <strong>Request return</strong>, and attach photos.</li><li>The seller responds within 48 hours; unresolved cases escalate to our team.</li><li>Approved refunds go back to your original payment method.</li></ul>',
                    'ms' => '<p>Anda boleh memohon pemulangan dalam tempoh <strong>7 hari</strong> selepas penghantaran untuk barangan rosak, salah, atau tidak seperti diterangkan.</p><ul><li>Pergi ke pesanan, pilih <strong>Mohon pemulangan</strong>, dan lampirkan gambar.</li><li>Penjual membalas dalam 48 jam; kes yang tidak selesai dibawa kepada pasukan kami.</li><li>Bayaran balik yang diluluskan dikembalikan ke kaedah pembayaran asal.</li></ul>',
                ],
            ],
            // ── Selling ─────────────────────────────────────────────────
            [
                'category' => 'selling', 'position' => 0,
                'title' => ['en' => 'Becoming a seller on HalalBizs', 'ms' => 'Menjadi penjual di HalalBizs'],
                'body' => [
                    'en' => '<p>Apply from <strong>Become a seller</strong> in the footer with your business details and documents.</p><ul><li>Applications are reviewed within 2 working days.</li><li>Once approved, you get a Seller Centre and a store page with its own address.</li><li>Listing products is free — the platform takes a small commission per sale.</li></ul>',
                    'ms' => '<p>Mohon melalui <strong>Jadi penjual</strong> di bahagian bawah halaman dengan butiran dan dokumen perniagaan anda.</p><ul><li>Permohonan disemak dalam 2 hari bekerja.</li><li>Selepas diluluskan, anda mendapat Pusat Penjual dan halaman kedai dengan alamat tersendiri.</li><li>Penyenaraian produk adalah percuma — platform mengambil komisen kecil bagi setiap jualan.</li></ul>',
                ],
            ],
            [
                'category' => 'selling', 'position' => 1,
                'title' => ['en' => 'When do sellers get paid? (payout schedule)', 'ms' => 'Bilakah penjual dibayar? (jadual pembayaran)'],
                'body' => [
                    'en' => '<p>Earnings move to your available balance when an order is <strong>completed</strong> — after delivery plus the 7-day return window.</p><ul><li>Request a payout from Seller Centre once your balance reaches RM50.</li><li>Payouts go to your registered bank account.</li><li>Commission is deducted automatically before earnings are credited.</li></ul>',
                    'ms' => '<p>Pendapatan berpindah ke baki tersedia apabila pesanan <strong>selesai</strong> — selepas penghantaran ditambah tempoh pemulangan 7 hari.</p><ul><li>Mohon pembayaran dari Pusat Penjual apabila baki mencecah RM50.</li><li>Pembayaran dibuat ke akaun bank berdaftar anda.</li><li>Komisen ditolak secara automatik sebelum pendapatan dikreditkan.</li></ul>',
                ],
            ],
            // ── Account ─────────────────────────────────────────────────
            [
                'category' => 'account', 'position' => 0,
                'title' => ['en' => 'Keeping your account secure', 'ms' => 'Memastikan akaun anda selamat'],
                'body' => [
                    'en' => '<p>A few habits keep your account safe:</p><ul><li>Use a unique password and enable <strong>two-factor authentication</strong> in account settings.</li><li>We never ask for your password, OTP, or banking PIN by chat, email, or phone.</li><li>Check the address bar — only log in on this site, never via links in messages.</li></ul><p>Think something is wrong? Change your password and contact support immediately.</p>',
                    'ms' => '<p>Beberapa amalan memastikan akaun anda selamat:</p><ul><li>Gunakan kata laluan unik dan aktifkan <strong>pengesahan dua faktor</strong> dalam tetapan akaun.</li><li>Kami tidak pernah meminta kata laluan, OTP, atau PIN perbankan anda melalui sembang, e-mel, atau telefon.</li><li>Semak bar alamat — hanya log masuk di laman ini, bukan melalui pautan dalam mesej.</li></ul><p>Rasa ada yang tidak kena? Tukar kata laluan dan hubungi sokongan segera.</p>',
                ],
            ],
        ];

        foreach ($articles as $article) {
            HelpArticle::updateOrCreate(
                ['category' => $article['category'], 'position' => $article['position']],
                [
                    'title' => $article['title'],
                    'body' => $article['body'],
                    'is_active' => true,
                ],
            );
        }
    }
}
