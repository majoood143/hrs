<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Region;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Seed the wilayats (cities/towns) of each Oman governorate.
     */
    public function run(): void
    {
        $citiesByRegion = [
            'Muscat' => [
                ['en_name' => 'Muscat', 'ar_name' => 'مسقط'],
                ['en_name' => 'Mutrah', 'ar_name' => 'مطرح'],
                ['en_name' => 'Bawshar', 'ar_name' => 'بوشر'],
                ['en_name' => 'Seeb', 'ar_name' => 'السيب'],
                ['en_name' => 'Al Amerat', 'ar_name' => 'العامرات'],
                ['en_name' => 'Qurayyat', 'ar_name' => 'قريات'],
            ],
            'Dhofar' => [
                ['en_name' => 'Salalah', 'ar_name' => 'صلالة'],
                ['en_name' => 'Taqah', 'ar_name' => 'طاقة'],
                ['en_name' => 'Mirbat', 'ar_name' => 'مرباط'],
                ['en_name' => 'Sadah', 'ar_name' => 'سدح'],
                ['en_name' => 'Rakhyut', 'ar_name' => 'رخيوت'],
                ['en_name' => 'Dhalkut', 'ar_name' => 'ضلكوت'],
                ['en_name' => 'Muqshin', 'ar_name' => 'مقشن'],
                ['en_name' => 'Thumrait', 'ar_name' => 'ثمريت'],
                ['en_name' => 'Shalim and the Hallaniyat Islands', 'ar_name' => 'شليم وجزر الحلانيات'],
                ['en_name' => 'Al Mazyunah', 'ar_name' => 'المزيونة'],
            ],
            'Musandam' => [
                ['en_name' => 'Khasab', 'ar_name' => 'خصب'],
                ['en_name' => 'Bukha', 'ar_name' => 'بخاء'],
                ['en_name' => 'Daba Al-Bayah', 'ar_name' => 'دبا البيعة'],
                ['en_name' => 'Madha', 'ar_name' => 'مدحاء'],
            ],
            'Al Buraimi' => [
                ['en_name' => 'Al Buraimi', 'ar_name' => 'البريمي'],
                ['en_name' => 'Mahdah', 'ar_name' => 'محضة'],
                ['en_name' => 'Al Sunaynah', 'ar_name' => 'السنينة'],
            ],
            'Ad Dakhiliyah' => [
                ['en_name' => 'Nizwa', 'ar_name' => 'نزوى'],
                ['en_name' => 'Bahla', 'ar_name' => 'بهلاء'],
                ['en_name' => 'Manah', 'ar_name' => 'منح'],
                ['en_name' => 'Al Hamra', 'ar_name' => 'الحمراء'],
                ['en_name' => 'Adam', 'ar_name' => 'ادم'],
                ['en_name' => 'Izki', 'ar_name' => 'إزكي'],
                ['en_name' => 'Samail', 'ar_name' => 'سمائل'],
                ['en_name' => 'Bidbid', 'ar_name' => 'بدبد'],
            ],
            'Al Batinah North' => [
                ['en_name' => 'Sohar', 'ar_name' => 'صحار'],
                ['en_name' => 'Shinas', 'ar_name' => 'شناص'],
                ['en_name' => 'Liwa', 'ar_name' => 'لوى'],
                ['en_name' => 'Saham', 'ar_name' => 'صحم'],
                ['en_name' => 'Al Khaburah', 'ar_name' => 'الخابورة'],
                ['en_name' => 'Rustaq', 'ar_name' => 'الرستاق'],
            ],
            'Al Batinah South' => [
                ['en_name' => 'Barka', 'ar_name' => 'بركاء'],
                ['en_name' => 'Al Musannah', 'ar_name' => 'المصنعة'],
                ['en_name' => 'Al Suwaiq', 'ar_name' => 'السويق'],
                ['en_name' => 'Nakhal', 'ar_name' => 'نخل'],
                ['en_name' => "Wadi Al Ma'awil", 'ar_name' => 'ودي المعاول'],
                ['en_name' => 'Al Awabi', 'ar_name' => 'العوابي'],
            ],
            'Al Sharqiyah North' => [
                ['en_name' => 'Ibra', 'ar_name' => 'إبراء'],
                ['en_name' => 'Al Mudhaibi', 'ar_name' => 'المضيبي'],
                ['en_name' => 'Bidiyah', 'ar_name' => 'بدية'],
                ['en_name' => 'Wadi Bani Khalid', 'ar_name' => 'وادي بني خالد'],
                ['en_name' => "Dima Wa Al Ta'iyin", 'ar_name' => 'دماء والطائيين'],
                ['en_name' => 'Al Qabil', 'ar_name' => 'القابل'],
            ],
            'Al Sharqiyah South' => [
                ['en_name' => 'Sur', 'ar_name' => 'صور'],
                ['en_name' => 'Al Kamil Wal Wafi', 'ar_name' => 'الكامل والوافي'],
                ['en_name' => 'Jalan Bani Bu Ali', 'ar_name' => 'جعلان بني بو علي'],
                ['en_name' => 'Jalan Bani Bu Hassan', 'ar_name' => 'جعلان بني بو حسن'],
                ['en_name' => 'Masirah', 'ar_name' => 'مصيرة'],
            ],
            'Ad Dhahirah' => [
                ['en_name' => 'Ibri', 'ar_name' => 'عبري'],
                ['en_name' => 'Yanqul', 'ar_name' => 'ينقل'],
                ['en_name' => 'Dhank', 'ar_name' => 'ضنك'],
            ],
            'Al Wusta' => [
                ['en_name' => 'Haima', 'ar_name' => 'هيماء'],
                ['en_name' => 'Duqm', 'ar_name' => 'الدقم'],
                ['en_name' => 'Mahout', 'ar_name' => 'محوت'],
                ['en_name' => 'Al Jazir', 'ar_name' => 'الجازر'],
            ],
        ];

        foreach ($citiesByRegion as $regionName => $cities) {
            $region = Region::query()->where('en_name', $regionName)->first();

            if (! $region) {
                continue;
            }

            foreach ($cities as $city) {
                City::query()->updateOrCreate(
                    ['region_id' => $region->id, 'en_name' => $city['en_name']],
                    array_merge($city, ['region_id' => $region->id])
                );
            }
        }
    }
}
