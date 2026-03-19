<?php

namespace App\Support;

final class Permissions
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::salesOptions());
    }

    /**
     * @return list<string>
     */
    public static function salesDefaults(): array
    {
        return [
            self::ViewProducts,
            self::CreateProducts,
            self::EditProducts,
            self::DeleteProducts,
            self::ViewSalesList,
            self::CreateSales,
            self::ViewSalesStats,
            self::ViewLatestSales,
        ];
    }

    /**
     * @return array<string, array{label:string, description:string}>
     */
    public static function salesOptions(): array
    {
        return [
            self::ViewProducts => [
                'label' => 'Lihat produk',
                'description' => 'Izinkan tim sales membuka katalog produk, mencari data, dan melihat stok serta harga.',
            ],
            self::CreateProducts => [
                'label' => 'Buat produk',
                'description' => 'Izinkan tim sales menambahkan produk baru ke katalog.',
            ],
            self::EditProducts => [
                'label' => 'Ubah produk',
                'description' => 'Izinkan tim sales memperbarui nama, harga, dan stok produk.',
            ],
            self::DeleteProducts => [
                'label' => 'Hapus produk',
                'description' => 'Izinkan tim sales menghapus produk yang belum pernah dipakai dalam transaksi.',
            ],
            self::ViewSalesList => [
                'label' => 'Lihat daftar penjualan',
                'description' => 'Izinkan tim sales membuka semua transaksi dan melihat detail item pada setiap penjualan.',
            ],
            self::CreateSales => [
                'label' => 'Entri penjualan',
                'description' => 'Izinkan tim sales mencatat transaksi baru dan menghitung komisi otomatis.',
            ],
            self::ViewReports => [
                'label' => 'Lihat laporan',
                'description' => 'Izinkan tim sales membuka rekap penjualan dan mengekspor file Excel.',
            ],
            self::ViewSalesStats => [
                'label' => 'Lihat statistik penjualan',
                'description' => 'Izinkan tim sales melihat kartu ringkasan, produk unggulan, dan pantauan stok di dashboard.',
            ],
            self::ViewLatestSales => [
                'label' => 'Lihat penjualan terbaru',
                'description' => 'Izinkan tim sales melihat daftar transaksi terbaru di dashboard.',
            ],
        ];
    }

    public const ViewProducts = 'view products';

    public const CreateProducts = 'create products';

    public const EditProducts = 'edit products';

    public const DeleteProducts = 'delete products';

    public const ViewSalesList = 'view sales list';

    public const CreateSales = 'create sales';

    public const ViewReports = 'view reports';

    public const ViewSalesStats = 'view sales stats';

    public const ViewLatestSales = 'view latest sales';
}
