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
        $this->seedAboutAndContactPages();
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

    private function seedAboutAndContactPages(): void
    {
        $pages = [
            [
                'slug' => 'about-us',
                'title' => ['en' => 'About Us', 'ar' => 'من نحن'],
                'meta_description' => [
                    'en' => 'Learn about the team and mission behind our horse transfer marketplace.',
                    'ar' => 'تعرف على الفريق والرسالة وراء منصتنا لنقل الخيول.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'heading' => ['en' => 'About Us', 'ar' => 'من نحن'],
                            'subheading' => [
                                'en' => 'We connect horse owners with caring, vetted transporters and stables across the region — because every horse deserves a safe journey.',
                                'ar' => 'نربط ملاك الخيول بناقلين وإسطبلات موثوقة ومهتمة في جميع أنحاء المنطقة — لأن كل حصان يستحق رحلة آمنة.',
                            ],
                        ],
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => ['en' => 'Our Story', 'ar' => 'قصتنا'],
                            'level' => 'h2',
                            'alignment' => 'center',
                        ],
                    ],
                    [
                        'type' => 'rich_text',
                        'data' => [
                            'content' => [
                                'en' => '<p>We started this platform because arranging a safe, trustworthy journey for a horse was harder than it should be. Today we bring together owners, transporters, stables, and veterinary partners on one platform built around care, transparency, and trust.</p>',
                                'ar' => '<p>أطلقنا هذه المنصة لأن ترتيب رحلة آمنة وموثوقة للحصان كان أصعب مما ينبغي. اليوم نجمع الملاك والناقلين والإسطبلات والشركاء البيطريين في منصة واحدة مبنية على الاهتمام والشفافية والثقة.</p>',
                            ],
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'items' => [
                                [
                                    'heading' => ['en' => 'Safety First', 'ar' => 'السلامة أولاً'],
                                    'text' => [
                                        'en' => 'Every transporter and partner is vetted so your horse travels in caring, capable hands.',
                                        'ar' => 'يتم التحقق من كل ناقل وشريك حتى يسافر حصانك في أيدٍ أمينة وقادرة.',
                                    ],
                                ],
                                [
                                    'heading' => ['en' => 'Transparency', 'ar' => 'الشفافية'],
                                    'text' => [
                                        'en' => 'Clear pricing, live updates, and direct contact with your transporter every step of the way.',
                                        'ar' => 'أسعار واضحة وتحديثات مباشرة وتواصل مباشر مع الناقل في كل خطوة من الرحلة.',
                                    ],
                                ],
                                [
                                    'heading' => ['en' => 'Community', 'ar' => 'المجتمع'],
                                    'text' => [
                                        'en' => 'A growing network of owners, stables, and clinics who care about horses as much as you do.',
                                        'ar' => 'شبكة متنامية من الملاك والإسطبلات والعيادات الذين يهتمون بالخيول بقدر اهتمامك.',
                                    ],
                                ],
                            ],
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
            ],
            [
                'slug' => 'contact-us',
                'title' => ['en' => 'Contact Us', 'ar' => 'تواصل معنا'],
                'meta_description' => [
                    'en' => 'Get in touch with our team by phone, email, or message.',
                    'ar' => 'تواصل مع فريقنا عبر الهاتف أو البريد الإلكتروني أو الرسائل.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'heading' => ['en' => 'Contact Us', 'ar' => 'تواصل معنا'],
                            'subheading' => [
                                'en' => 'Questions about a transfer, a listing, or becoming a partner? We would love to hear from you.',
                                'ar' => 'لديك أسئلة حول نقلة أو إعلان أو الانضمام كشريك؟ يسعدنا أن نسمع منك.',
                            ],
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'items' => [
                                [
                                    'heading' => ['en' => 'Phone', 'ar' => 'الهاتف'],
                                    'text' => ['en' => '+968 9000 0000', 'ar' => '968 9000 0000+'],
                                ],
                                [
                                    'heading' => ['en' => 'Email', 'ar' => 'البريد الإلكتروني'],
                                    'text' => ['en' => 'hello@horsetransfer.example', 'ar' => 'hello@horsetransfer.example'],
                                ],
                                [
                                    'heading' => ['en' => 'Hours', 'ar' => 'ساعات العمل'],
                                    'text' => [
                                        'en' => 'Sunday – Thursday, 9:00 AM – 6:00 PM',
                                        'ar' => 'الأحد إلى الخميس، 9:00 صباحًا – 6:00 مساءً',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'type' => 'cta',
                        'data' => [
                            'heading' => ['en' => 'Send us a message', 'ar' => 'أرسل لنا رسالة'],
                            'subheading' => [
                                'en' => 'Email our team and we will get back to you as soon as possible.',
                                'ar' => 'راسل فريقنا وسنرد عليك في أقرب وقت ممكن.',
                            ],
                            'button_text' => ['en' => 'Email Us', 'ar' => 'راسلنا'],
                            'button_url' => 'mailto:hello@horsetransfer.example',
                            'style' => 'dark',
                        ],
                    ],
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
                    'is_system' => false,
                    'show_title' => false,
                    'content' => $data['content'],
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

        $aboutPage = CmsPage::where('slug', 'about-us')->first();
        $contactPage = CmsPage::where('slug', 'contact-us')->first();

        foreach ([$header, $footer] as $menu) {
            if ($aboutPage && $menu->allItems()->where('page_id', $aboutPage->id)->doesntExist()) {
                $menu->allItems()->create([
                    'label' => ['en' => 'About Us', 'ar' => 'من نحن'],
                    'page_id' => $aboutPage->id,
                    'order' => 3,
                ]);
            }

            if ($contactPage && $menu->allItems()->where('page_id', $contactPage->id)->doesntExist()) {
                $menu->allItems()->create([
                    'label' => ['en' => 'Contact Us', 'ar' => 'تواصل معنا'],
                    'page_id' => $contactPage->id,
                    'order' => 4,
                ]);
            }
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
