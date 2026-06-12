<?php

namespace Database\Seeders;

use App\Models\CancellationReason;
use App\Models\ReturnReason;
use Illuminate\Database\Seeder;

class ReasonSeeder extends Seeder
{
    public function run(): void
    {
        $returnReasons = [
            ['Item is damaged or defective', 'Barang rosak atau cacat'],
            ['Wrong item received', 'Barang salah diterima'],
            ['Item does not match description', 'Barang tidak seperti diterangkan'],
            ['Missing parts or accessories', 'Bahagian atau aksesori hilang'],
            ['Changed my mind', 'Berubah fikiran'],
        ];

        $cancellationReasons = [
            ['Need to change delivery address', 'Perlu menukar alamat penghantaran'],
            ['Found a better price elsewhere', 'Jumpa harga lebih baik di tempat lain'],
            ['Ordered by mistake', 'Tersilap pesan'],
            ['Seller is taking too long to ship', 'Penjual mengambil masa terlalu lama'],
            ['Changed my mind', 'Berubah fikiran'],
        ];

        foreach ($returnReasons as $i => [$en, $ms]) {
            ReturnReason::updateOrCreate(
                ['position' => $i],
                ['label' => ['en' => $en, 'ms' => $ms], 'is_active' => true],
            );
        }

        foreach ($cancellationReasons as $i => [$en, $ms]) {
            CancellationReason::updateOrCreate(
                ['position' => $i],
                ['label' => ['en' => $en, 'ms' => $ms], 'is_active' => true],
            );
        }
    }
}
