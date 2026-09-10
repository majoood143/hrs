<?php

namespace Database\Seeders;

use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\Partner;
use App\Models\SiteSetting;
use App\Models\SuccessStory;
use Illuminate\Database\Seeder;

class HorseWebsiteSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSiteSettings();
        $this->seedHomepage();
        $this->seedModulePages();
        $this->seedMenus();
        $this->seedPartners();
        $this->seedSuccessStories();
    }

    private function seedSiteSettings(): void
    {
        SiteSetting::set('footer_text_en', 'Connecting horses and the people who love them with safe, caring journeys — every time.', 'richtext', 'Text shown in the site footer (English).', 'general_settings');
        SiteSetting::set('footer_text_ar', 'نربط بين الخيول ومحبيها برحلات آمنة ومليئة بالاهتمام — في كل مرة.', 'richtext', 'Text shown in the site footer (Arabic).', 'general_settings');
        SiteSetting::set('contact_phone', '+968 9000 0000', 'text', 'Public contact phone number.', 'general_settings');
        SiteSetting::set('contact_email', 'hello@horsetransfer.example', 'text', 'Public contact email address.', 'general_settings');
    }

    private function seedHomepage(): void
    {
        if (CmsPage::query()->where('is_homepage', true)->exists()) {
            return;
        }

        CmsPage::create([
            'title' => ['en' => 'Home', 'ar' => 'الرئيسية'],
            'slug' => 'home',
            'status' => 'published',
            'published_at' => now(),
            'is_homepage' => true,
            'show_title' => false,
            'content' => [
                [
                    'type' => 'hero',
                    'data' => [
                        'heading' => [
                            'en' => 'Every journey, a little more like home',
                            'ar' => 'كل رحلة، أقرب قليلًا إلى الوطن',
                        ],
                        'subheading' => [
                            'en' => 'From the first phone call to the final hoofbeat off the trailer, we treat every horse like our own — matched with caring, vetted transporters across the region.',
                            'ar' => 'من أول اتصال هاتفي وحتى آخر خطوة للحصان خارج المقطورة، نتعامل مع كل حصان وكأنه ملكنا — مع نقل موثوق ومهتم في جميع أنحاء المنطقة.',
                        ],
                        'primary_button_text' => ['en' => 'Find a Transfer', 'ar' => 'ابحث عن نقلة'],
                        'primary_button_url' => '/transportation',
                        'secondary_button_text' => ['en' => 'Post a Request', 'ar' => 'انشر طلبًا'],
                        'secondary_button_url' => '/transportation/post',
                    ],
                ],
                [
                    'type' => 'search_preview',
                    'data' => [
                        'heading' => ['en' => 'Find a safe ride for your horse', 'ar' => 'ابحث عن رحلة آمنة لحصانك'],
                        'subheading' => [
                            'en' => 'Search live offers and requests from trusted transporters near you.',
                            'ar' => 'ابحث عن عروض وطلبات مباشرة من ناقلين موثوقين بالقرب منك.',
                        ],
                    ],
                ],
                [
                    'type' => 'featured_horses',
                    'data' => [
                        'heading' => ['en' => 'Meet the horses', 'ar' => 'تعرف على الخيول'],
                        'subheading' => [
                            'en' => 'A few of the horses who have found safe, comfortable journeys through our platform.',
                            'ar' => 'بعض الخيول التي وجدت رحلات آمنة ومريحة عبر منصتنا.',
                        ],
                        'count' => 6,
                    ],
                ],
                [
                    'type' => 'stats_counter',
                    'data' => [
                        'heading' => ['en' => 'Trusted by owners across the region', 'ar' => 'موثوق من قبل الملاك في جميع أنحاء المنطقة'],
                        'stats' => [
                            ['number' => '1200', 'suffix' => '+', 'label' => ['en' => 'Horses Transferred', 'ar' => 'خيول تم نقلها']],
                            ['number' => '98', 'suffix' => '%', 'label' => ['en' => 'Safe Arrivals', 'ar' => 'وصول آمن']],
                            ['number' => '40', 'suffix' => '+', 'label' => ['en' => 'Partner Stables', 'ar' => 'إسطبلات شريكة']],
                        ],
                    ],
                ],
                [
                    'type' => 'success_stories',
                    'data' => [
                        'heading' => ['en' => 'Happy endings', 'ar' => 'نهايات سعيدة'],
                        'subheading' => [
                            'en' => 'Real stories from owners who trusted us with their horses.',
                            'ar' => 'قصص حقيقية من ملاك وثقوا بنا مع خيولهم.',
                        ],
                        'count' => 3,
                    ],
                ],
                [
                    'type' => 'partners',
                    'data' => [
                        'heading' => ['en' => 'Trusted partners', 'ar' => 'شركاء موثوقون'],
                        'subheading' => [
                            'en' => 'The stables, transporters, and vets who help make every journey safe.',
                            'ar' => 'الإسطبلات والناقلون والأطباء البيطريون الذين يساهمون في جعل كل رحلة آمنة.',
                        ],
                    ],
                ],
                [
                    'type' => 'cta',
                    'data' => [
                        'heading' => [
                            'en' => 'Ready to give your horse a caring journey?',
                            'ar' => 'هل أنت مستعد لرحلة مليئة بالاهتمام لحصانك؟',
                        ],
                        'subheading' => [
                            'en' => 'Post your transfer in minutes and connect with a trusted transporter today.',
                            'ar' => 'انشر طلب النقل في دقائق وتواصل مع ناقل موثوق اليوم.',
                        ],
                        'button_text' => ['en' => 'Book a Transfer', 'ar' => 'احجز نقلة'],
                        'button_url' => '/transportation',
                        'style' => 'warm',
                    ],
                ],
            ],
        ]);
    }

    /**
     * These slugs are excluded from the catch-all CMS page route (see routes/web.php)
     * because they're served by dedicated controllers. The CmsPage record here exists
     * only so admins can control that route's SEO metadata from the Pages screen.
     */
    private function seedModulePages(): void
    {
        $pages = [
            [
                'slug' => 'transfer-board',
                'title' => ['en' => 'Horse Transfer Board', 'ar' => 'لوحة نقل الخيول'],
                'meta_description' => [
                    'en' => 'Browse live horse transfer offers and requests, or post your own — connect with trusted transporters near you.',
                    'ar' => 'تصفح عروض وطلبات نقل الخيول المباشرة، أو انشر طلبك الخاص — تواصل مع ناقلين موثوقين بالقرب منك.',
                ],
            ],
            [
                'slug' => 'horses-for-sale',
                'title' => ['en' => 'Horses for Sale', 'ar' => 'خيول للبيع'],
                'meta_description' => [
                    'en' => 'Discover horses for sale from sellers across the region, with verified listings and direct contact.',
                    'ar' => 'اكتشف خيولاً للبيع من بائعين في جميع أنحاء المنطقة، مع إعلانات موثقة وتواصل مباشر.',
                ],
            ],
            [
                'slug' => 'stables',
                'title' => ['en' => 'Stables', 'ar' => 'الإسطبلات'],
                'meta_description' => [
                    'en' => 'Find trusted stables offering boarding, training, and care for your horse.',
                    'ar' => 'ابحث عن إسطبلات موثوقة تقدم خدمات الإيواء والتدريب والعناية بحصانك.',
                ],
            ],
            [
                'slug' => 'clinics',
                'title' => ['en' => 'Clinics', 'ar' => 'العيادات'],
                'meta_description' => [
                    'en' => 'Explore veterinary clinics and equine health services near you.',
                    'ar' => 'استكشف العيادات البيطرية وخدمات الرعاية الصحية للخيول بالقرب منك.',
                ],
            ],
            [
                'slug' => 'farriers',
                'title' => ['en' => 'Farriers', 'ar' => 'البياطرة'],
                'meta_description' => [
                    'en' => 'Connect with experienced farriers for hoof care and shoeing services.',
                    'ar' => 'تواصل مع بياطرة ذوي خبرة لخدمات العناية بالحوافر والتنعيل.',
                ],
            ],
            [
                'slug' => 'tools-for-sale',
                'title' => ['en' => 'Tools for Sale', 'ar' => 'أدوات للبيع'],
                'meta_description' => [
                    'en' => 'Buy and sell horse care tools, tack, and equipment.',
                    'ar' => 'بيع وشراء أدوات ومعدات العناية بالخيول.',
                ],
            ],
        ];

        foreach ($pages as $data) {
            CmsPage::firstOrCreate(
                ['slug' => $data['slug']],
                [
                    'title' => $data['title'],
                    'meta_title' => $data['title'],
                    'meta_description' => $data['meta_description'],
                    'status' => 'published',
                    'published_at' => now(),
                    'is_homepage' => false,
                    'is_system' => true,
                    'show_title' => false,
                    'content' => [],
                ]
            );
        }
    }

    private function seedMenus(): void
    {
        $header = CmsMenu::firstOrCreate(
            ['slug' => 'header-menu'],
            ['name' => 'Header Menu', 'location' => 'header']
        );

        if ($header->allItems()->doesntExist()) {
            $header->allItems()->createMany([
                ['label' => ['en' => 'Home', 'ar' => 'الرئيسية'], 'url' => '/', 'order' => 1],
                ['label' => ['en' => 'Stories', 'ar' => 'قصص'], 'url' => '/blog', 'order' => 2],
            ]);
        }

        $footer = CmsMenu::firstOrCreate(
            ['slug' => 'footer-menu'],
            ['name' => 'Footer Menu', 'location' => 'footer']
        );

        if ($footer->allItems()->doesntExist()) {
            $footer->allItems()->createMany([
                ['label' => ['en' => 'Transfer Board', 'ar' => 'لوحة النقلات'], 'url' => '/transportation', 'order' => 1],
                ['label' => ['en' => 'Stories', 'ar' => 'قصص'], 'url' => '/blog', 'order' => 2],
            ]);
        }
    }

    private function seedPartners(): void
    {
        if (Partner::query()->exists()) {
            return;
        }

        $partners = [
            ['en_name' => 'Al Wafa Stables', 'ar_name' => 'إسطبلات الوفاء', 'type' => 'stable', 'order' => 1],
            ['en_name' => 'SafeRide Horse Transport', 'ar_name' => 'سيف رايد لنقل الخيول', 'type' => 'transport', 'order' => 2],
            ['en_name' => 'Gulf Equine Vet Clinic', 'ar_name' => 'عيادة الخليج البيطرية للخيول', 'type' => 'veterinary', 'order' => 3],
            ['en_name' => 'Regional Equestrian Authority', 'ar_name' => 'الهيئة الإقليمية للفروسية', 'type' => 'authority', 'order' => 4],
        ];

        foreach ($partners as $partner) {
            Partner::create($partner + ['is_active' => true]);
        }
    }

    private function seedSuccessStories(): void
    {
        if (SuccessStory::query()->exists()) {
            return;
        }

        $stories = [
            [
                'owner_name' => 'Fatima Al Balushi',
                'en_route' => 'Muscat → Dubai',
                'ar_route' => 'مسقط ← دبي',
                'en_quote' => 'They treated my mare like family from the first call. She arrived calm, comfortable, and right on time.',
                'ar_quote' => 'تعاملوا مع فرستي وكأنها فرد من العائلة منذ أول اتصال. وصلت هادئة ومرتاحة وفي الوقت المحدد تمامًا.',
                'order' => 1,
            ],
            [
                'owner_name' => 'Khalid Al Hinai',
                'en_route' => 'Salalah → Muscat',
                'ar_route' => 'صلالة ← مسقط',
                'en_quote' => 'Booking a transfer took minutes, and the driver sent updates the whole way. I would not trust anyone else with my horses.',
                'ar_quote' => 'استغرق حجز النقل دقائق فقط، وأرسل السائق تحديثات طوال الرحلة. لن أثق بأحد آخر مع خيولي.',
                'order' => 2,
            ],
            [
                'owner_name' => 'Sara Al Kindi',
                'en_route' => 'Nizwa → Sohar',
                'ar_route' => 'نزوى ← صحار',
                'en_quote' => 'A genuinely caring team. My colt was nervous about travel, and they made the whole experience gentle and reassuring.',
                'ar_quote' => 'فريق يهتم حقًا. كان مهري متوترًا بشأن السفر، فجعلوا التجربة كلها لطيفة ومطمئنة.',
                'order' => 3,
            ],
        ];

        foreach ($stories as $story) {
            SuccessStory::create($story + ['is_published' => true]);
        }
    }
}
