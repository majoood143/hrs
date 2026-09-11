<?php

use App\Models\CmsMenu;
use App\Models\CmsPage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $siteNameEn = \App\Models\SiteSetting::get('site_name_en', config('app.name', 'HRS'));
        $siteNameAr = \App\Models\SiteSetting::get('site_name_ar', config('app.name', 'HRS'));

        $privacyEn = <<<HTML
        <h2>1. Introduction</h2>
        <p>{$siteNameEn} ("we", "us", or "our") operates a marketplace that connects horse owners, transporters, stables, clinics, and farriers. This Privacy Policy explains what information we collect, how we use it, and the choices you have.</p>

        <h2>2. Information We Collect</h2>
        <ul>
            <li>Account information you provide, such as your name, email address, phone number, and password.</li>
            <li>Listing and transaction details, such as transfer requests, horse details, and messages exchanged on the platform.</li>
            <li>Technical information, such as your IP address, browser type, device identifiers, and pages you visit.</li>
            <li>Information you submit through forms, such as contact requests or support messages.</li>
        </ul>

        <h2>3. How We Use Your Information</h2>
        <ul>
            <li>To create and manage your account and provide the services you request.</li>
            <li>To connect owners with transporters, stables, clinics, and farriers.</li>
            <li>To communicate with you about your account, transfers, and platform updates.</li>
            <li>To maintain the safety, security, and integrity of the platform.</li>
            <li>To improve our services and understand how the platform is used.</li>
        </ul>

        <h2>4. Cookies and Tracking Technologies</h2>
        <p>We use cookies and similar technologies to keep you signed in, remember your language preference, and understand how visitors use our site. You can control cookies through your browser settings, though disabling them may affect some features.</p>

        <h2>5. Sharing of Information</h2>
        <p>We do not sell your personal information. We may share information with transporters, stables, clinics, or farriers you choose to contact, with service providers who help us operate the platform, and when required by law.</p>

        <h2>6. Data Security</h2>
        <p>We use reasonable administrative, technical, and physical safeguards to protect your information. No method of transmission or storage is completely secure, so we cannot guarantee absolute security.</p>

        <h2>7. Data Retention</h2>
        <p>We retain your information for as long as your account is active or as needed to provide our services, comply with legal obligations, resolve disputes, and enforce our agreements.</p>

        <h2>8. Your Rights</h2>
        <p>You may access, correct, or request deletion of your personal information by contacting us. You may also update most of your account information directly from your profile settings.</p>

        <h2>9. Children's Privacy</h2>
        <p>Our platform is not directed to individuals under the age of 18, and we do not knowingly collect personal information from children.</p>

        <h2>10. Changes to This Policy</h2>
        <p>We may update this Privacy Policy from time to time. We will post the updated version on this page with a new effective date.</p>

        <h2>11. Contact Us</h2>
        <p>If you have questions about this Privacy Policy or how we handle your information, please contact us through the details on our Contact page.</p>
        HTML;

        $privacyAr = <<<HTML
        <h2>1. مقدمة</h2>
        <p>تدير {$siteNameAr} ("نحن" أو "لنا") منصة تربط بين ملاك الخيول والناقلين والإسطبلات والعيادات والبياطرة. توضح سياسة الخصوصية هذه المعلومات التي نجمعها، وكيفية استخدامها، والخيارات المتاحة لك.</p>

        <h2>2. المعلومات التي نجمعها</h2>
        <ul>
            <li>معلومات الحساب التي تقدمها، مثل الاسم والبريد الإلكتروني ورقم الهاتف وكلمة المرور.</li>
            <li>تفاصيل الإعلانات والمعاملات، مثل طلبات النقل وبيانات الخيول والرسائل المتبادلة على المنصة.</li>
            <li>معلومات تقنية، مثل عنوان IP ونوع المتصفح ومعرّفات الجهاز والصفحات التي تزورها.</li>
            <li>المعلومات التي ترسلها عبر النماذج، مثل طلبات التواصل أو رسائل الدعم.</li>
        </ul>

        <h2>3. كيفية استخدام معلوماتك</h2>
        <ul>
            <li>لإنشاء حسابك وإدارته وتقديم الخدمات التي تطلبها.</li>
            <li>لربط الملاك بالناقلين والإسطبلات والعيادات والبياطرة.</li>
            <li>للتواصل معك بشأن حسابك وعمليات النقل وتحديثات المنصة.</li>
            <li>للحفاظ على سلامة المنصة وأمانها وسلامتها.</li>
            <li>لتحسين خدماتنا وفهم كيفية استخدام المنصة.</li>
        </ul>

        <h2>4. ملفات تعريف الارتباط وتقنيات التتبع</h2>
        <p>نستخدم ملفات تعريف الارتباط وتقنيات مشابهة لإبقائك مسجلاً للدخول، وتذكر تفضيل اللغة لديك، وفهم كيفية استخدام الزوار لموقعنا. يمكنك التحكم في ملفات تعريف الارتباط من خلال إعدادات متصفحك، مع العلم أن تعطيلها قد يؤثر على بعض الميزات.</p>

        <h2>5. مشاركة المعلومات</h2>
        <p>نحن لا نبيع معلوماتك الشخصية. قد نشارك المعلومات مع الناقلين أو الإسطبلات أو العيادات أو البياطرة الذين تختار التواصل معهم، ومع مزودي الخدمات الذين يساعدوننا في تشغيل المنصة، وعند اقتضاء القانون ذلك.</p>

        <h2>6. أمن البيانات</h2>
        <p>نستخدم إجراءات إدارية وتقنية ومادية معقولة لحماية معلوماتك. لا توجد وسيلة نقل أو تخزين آمنة بشكل كامل، لذا لا يمكننا ضمان الأمان المطلق.</p>

        <h2>7. الاحتفاظ بالبيانات</h2>
        <p>نحتفظ بمعلوماتك طالما كان حسابك نشطًا أو حسب الحاجة لتقديم خدماتنا، والامتثال للالتزامات القانونية، وحل النزاعات، وإنفاذ اتفاقياتنا.</p>

        <h2>8. حقوقك</h2>
        <p>يمكنك الوصول إلى معلوماتك الشخصية أو تصحيحها أو طلب حذفها من خلال التواصل معنا. يمكنك أيضًا تحديث معظم معلومات حسابك مباشرة من إعدادات ملفك الشخصي.</p>

        <h2>9. خصوصية الأطفال</h2>
        <p>منصتنا غير موجهة للأفراد الذين تقل أعمارهم عن 18 عامًا، ولا نقوم عن قصد بجمع معلومات شخصية من الأطفال.</p>

        <h2>10. التغييرات على هذه السياسة</h2>
        <p>قد نقوم بتحديث سياسة الخصوصية هذه من وقت لآخر. سننشر النسخة المحدثة على هذه الصفحة مع تاريخ سريان جديد.</p>

        <h2>11. تواصل معنا</h2>
        <p>إذا كانت لديك أسئلة حول سياسة الخصوصية هذه أو كيفية تعاملنا مع معلوماتك، يرجى التواصل معنا عبر التفاصيل الموجودة في صفحة التواصل.</p>
        HTML;

        $termsEn = <<<HTML
        <h2>1. Acceptance of Terms</h2>
        <p>By accessing or using {$siteNameEn}, you agree to be bound by these Terms and Conditions. If you do not agree, please do not use the platform.</p>

        <h2>2. Eligibility and Accounts</h2>
        <p>You must be at least 18 years old to create an account. You are responsible for maintaining the confidentiality of your account credentials and for all activity that occurs under your account.</p>

        <h2>3. Use of the Platform</h2>
        <p>The platform allows owners to post transfer requests and connect with transporters, stables, clinics, and farriers. You agree to provide accurate information and to use the platform only for lawful purposes.</p>

        <h2>4. Listings, Transfers, and Transactions</h2>
        <p>We do not own, operate, or control the horses, transportation services, stables, clinics, or farrier services listed on the platform. Any agreement, transfer, or transaction you enter into with another user is solely between you and that user, and you are responsible for verifying credentials, safety, and suitability before proceeding.</p>

        <h2>5. Payments and Fees</h2>
        <p>Any fees charged for use of the platform will be disclosed to you before you incur them. Payments made directly between users for transfers, boarding, veterinary, or farrier services are the responsibility of the parties involved and are not processed or guaranteed by us.</p>

        <h2>6. User Conduct</h2>
        <ul>
            <li>Do not post false, misleading, or fraudulent information.</li>
            <li>Do not use the platform to harass, threaten, or harm others.</li>
            <li>Do not attempt to interfere with the security or operation of the platform.</li>
            <li>Do not use the platform for any activity that violates applicable law.</li>
        </ul>

        <h2>7. Intellectual Property</h2>
        <p>All content on the platform, including text, graphics, logos, and software, is owned by us or our licensors and is protected by applicable intellectual property laws. You may not copy, modify, or distribute this content without our permission.</p>

        <h2>8. Disclaimers</h2>
        <p>The platform is provided "as is" without warranties of any kind. We do not guarantee the accuracy, quality, safety, or legality of listings, transfers, or services offered by users, and we do not guarantee that the platform will be uninterrupted or error-free.</p>

        <h2>9. Limitation of Liability</h2>
        <p>To the fullest extent permitted by law, we are not liable for any indirect, incidental, or consequential damages arising from your use of the platform, or from any interaction, transfer, or transaction with another user.</p>

        <h2>10. Indemnification</h2>
        <p>You agree to indemnify and hold us harmless from any claims, damages, or expenses arising from your use of the platform or your violation of these Terms.</p>

        <h2>11. Termination</h2>
        <p>We may suspend or terminate your account at any time if you violate these Terms or if we believe it is necessary to protect the platform or other users.</p>

        <h2>12. Governing Law</h2>
        <p>These Terms are governed by the applicable laws of the jurisdiction in which we operate, without regard to conflict of law principles.</p>

        <h2>13. Changes to These Terms</h2>
        <p>We may update these Terms from time to time. Continued use of the platform after changes take effect constitutes acceptance of the revised Terms.</p>

        <h2>14. Contact Us</h2>
        <p>If you have questions about these Terms and Conditions, please contact us through the details on our Contact page.</p>
        HTML;

        $termsAr = <<<HTML
        <h2>1. قبول الشروط</h2>
        <p>باستخدامك أو دخولك إلى {$siteNameAr}، فإنك توافق على الالتزام بهذه الشروط والأحكام. إذا كنت لا توافق عليها، يرجى عدم استخدام المنصة.</p>

        <h2>2. الأهلية والحسابات</h2>
        <p>يجب أن يكون عمرك 18 عامًا على الأقل لإنشاء حساب. أنت مسؤول عن الحفاظ على سرية بيانات حسابك وعن جميع الأنشطة التي تتم من خلاله.</p>

        <h2>3. استخدام المنصة</h2>
        <p>تتيح المنصة للملاك نشر طلبات النقل والتواصل مع الناقلين والإسطبلات والعيادات والبياطرة. أنت توافق على تقديم معلومات دقيقة واستخدام المنصة للأغراض القانونية فقط.</p>

        <h2>4. الإعلانات وعمليات النقل والمعاملات</h2>
        <p>نحن لا نملك أو ندير أو نتحكم في الخيول أو خدمات النقل أو الإسطبلات أو العيادات أو خدمات البياطرة المدرجة على المنصة. أي اتفاق أو عملية نقل أو معاملة تدخل فيها مع مستخدم آخر تكون بينك وبين ذلك المستخدم فقط، وأنت مسؤول عن التحقق من المؤهلات والسلامة والملاءمة قبل المتابعة.</p>

        <h2>5. المدفوعات والرسوم</h2>
        <p>سيتم الإفصاح لك عن أي رسوم مقابل استخدام المنصة قبل تحملها. المدفوعات التي تتم مباشرة بين المستخدمين مقابل خدمات النقل أو الإيواء أو الخدمات البيطرية أو خدمات البياطرة هي مسؤولية الأطراف المعنية ولا نقوم بمعالجتها أو ضمانها.</p>

        <h2>6. سلوك المستخدم</h2>
        <ul>
            <li>عدم نشر معلومات كاذبة أو مضللة أو احتيالية.</li>
            <li>عدم استخدام المنصة لمضايقة الآخرين أو تهديدهم أو إيذائهم.</li>
            <li>عدم محاولة التدخل في أمان المنصة أو طريقة عملها.</li>
            <li>عدم استخدام المنصة لأي نشاط يخالف القانون المعمول به.</li>
        </ul>

        <h2>7. الملكية الفكرية</h2>
        <p>جميع المحتويات الموجودة على المنصة، بما في ذلك النصوص والرسومات والشعارات والبرمجيات، مملوكة لنا أو للجهات المرخصة لنا، وهي محمية بموجب قوانين الملكية الفكرية المعمول بها. لا يجوز نسخ هذا المحتوى أو تعديله أو توزيعه دون إذننا.</p>

        <h2>8. إخلاء المسؤولية</h2>
        <p>يتم تقديم المنصة "كما هي" دون أي ضمانات من أي نوع. نحن لا نضمن دقة أو جودة أو سلامة أو قانونية الإعلانات أو عمليات النقل أو الخدمات التي يقدمها المستخدمون، ولا نضمن أن تعمل المنصة دون انقطاع أو أخطاء.</p>

        <h2>9. تحديد المسؤولية</h2>
        <p>إلى أقصى حد يسمح به القانون، لسنا مسؤولين عن أي أضرار غير مباشرة أو عرضية أو تبعية تنشأ عن استخدامك للمنصة، أو عن أي تفاعل أو عملية نقل أو معاملة مع مستخدم آخر.</p>

        <h2>10. التعويض</h2>
        <p>أنت توافق على تعويضنا وإبراء ذمتنا من أي مطالبات أو أضرار أو نفقات تنشأ عن استخدامك للمنصة أو مخالفتك لهذه الشروط.</p>

        <h2>11. الإنهاء</h2>
        <p>يجوز لنا تعليق حسابك أو إنهاؤه في أي وقت إذا خالفت هذه الشروط أو إذا رأينا أن ذلك ضروري لحماية المنصة أو المستخدمين الآخرين.</p>

        <h2>12. القانون الحاكم</h2>
        <p>تخضع هذه الشروط للقوانين المعمول بها في الولاية القضائية التي نعمل فيها، بغض النظر عن مبادئ تنازع القوانين.</p>

        <h2>13. التغييرات على هذه الشروط</h2>
        <p>قد نقوم بتحديث هذه الشروط من وقت لآخر. يُعد استمرارك في استخدام المنصة بعد سريان التغييرات موافقة منك على الشروط المعدّلة.</p>

        <h2>14. تواصل معنا</h2>
        <p>إذا كانت لديك أسئلة حول هذه الشروط والأحكام، يرجى التواصل معنا عبر التفاصيل الموجودة في صفحة التواصل.</p>
        HTML;

        $pages = [
            [
                'slug' => 'privacy-policy',
                'title' => ['en' => 'Privacy Policy', 'ar' => 'سياسة الخصوصية'],
                'meta_description' => [
                    'en' => 'Read how we collect, use, and protect your information.',
                    'ar' => 'اطّلع على كيفية جمعنا لمعلوماتك واستخدامها وحمايتها.',
                ],
                'content' => [
                    [
                        'type' => 'rich_text',
                        'data' => [
                            'content' => ['en' => $privacyEn, 'ar' => $privacyAr],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'terms-and-conditions',
                'title' => ['en' => 'Terms & Conditions', 'ar' => 'الشروط والأحكام'],
                'meta_description' => [
                    'en' => 'The terms and conditions that govern your use of the platform.',
                    'ar' => 'الشروط والأحكام التي تحكم استخدامك للمنصة.',
                ],
                'content' => [
                    [
                        'type' => 'rich_text',
                        'data' => [
                            'content' => ['en' => $termsEn, 'ar' => $termsAr],
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
                    'is_system' => true,
                    'show_title' => true,
                    'content' => $data['content'],
                ]
            );
        }

        $legalMenu = CmsMenu::firstOrCreate(
            ['slug' => 'footer-legal-menu'],
            ['name' => 'Footer Legal Menu', 'location' => 'footer_legal']
        );

        $privacyPage = CmsPage::where('slug', 'privacy-policy')->first();
        $termsPage = CmsPage::where('slug', 'terms-and-conditions')->first();

        if ($privacyPage && $legalMenu->allItems()->where('page_id', $privacyPage->id)->doesntExist()) {
            $legalMenu->allItems()->create([
                'label' => ['en' => 'Privacy Policy', 'ar' => 'سياسة الخصوصية'],
                'page_id' => $privacyPage->id,
                'order' => 1,
            ]);
        }

        if ($termsPage && $legalMenu->allItems()->where('page_id', $termsPage->id)->doesntExist()) {
            $legalMenu->allItems()->create([
                'label' => ['en' => 'Terms & Conditions', 'ar' => 'الشروط والأحكام'],
                'page_id' => $termsPage->id,
                'order' => 2,
            ]);
        }
    }

    public function down(): void
    {
        $legalMenu = CmsMenu::query()->where('slug', 'footer-legal-menu')->first();

        if ($legalMenu) {
            $legalMenu->allItems()->delete();
            $legalMenu->delete();
        }

        CmsPage::query()->whereIn('slug', ['privacy-policy', 'terms-and-conditions'])->delete();
    }
};
