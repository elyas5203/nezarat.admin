// --- Global Constants ---
const WEB_APP_URL = 'https://script.google.com/macros/s/AKfycby2A79FzX-kgKH04lZki5AGOD8DCvN8KsbU8E3CDm9jddeci48w0542qA-AwLV7y4opVA/exec';
const ADMIN_USERNAME = 'nezarat';
const ADMIN_PASSWORD_HASH = '1a0874f57f223ede978ea6650d925ec99bf31dfbb299655f9239e63adb69cbb5';

// --- DOM Elements (Admin Specific) ---
const adminLoginSection = document.getElementById('adminLoginSection');
const adminDashboardSection = document.getElementById('adminDashboardSection');
const adminContentArea = document.getElementById('adminContentArea');
const adminMenu = document.getElementById('adminMenu');
const adminLoginMessageArea = document.getElementById('adminLoginMessageArea');
const adminWelcomeMessage = document.getElementById('adminWelcomeMessage');

const addClassMessageArea = document.getElementById('addClassMessageArea');
const classListDiv = document.getElementById('classList');

const addUserMessageArea = document.getElementById('addUserMessageArea');
const usersListContainer = document.getElementById('usersListContainer');

const visitAssignmentsTableContainer = document.getElementById('visitAssignmentsTableContainer');
const visitAssignmentsMessageArea = document.getElementById('visitAssignmentsMessageArea');

const viewResultsMessageArea = document.getElementById('viewResultsMessageArea');
const resultsViewerSection = document.getElementById('resultsViewerSection');
const formDisplayForAdmin = document.getElementById('formDisplayForAdmin');
const formDisplayForAdminNav = document.getElementById('formDisplayForAdminNav');
const adminFormViewInfo = document.getElementById('adminFormViewInfo');
const downloadPdfButton = document.getElementById('downloadPdfButton');
const resultsViewerTitle = document.getElementById('resultsViewerTitle');

// Analytics (Partial/Detailed) Section Elements
const analyticsClassCodeSelect = document.getElementById('analyticsClassCodeSelect');
const analyticsVisitPasswordInput = document.getElementById('analyticsVisitPassword');
const analyticsDisplayArea = document.getElementById('analyticsDisplayArea');
const analyticsMessageArea = document.getElementById('analyticsMessageArea');
const visitPasswordControlGroupAnalytics = document.getElementById('visitPasswordControlGroupAnalytics');

// Trend Analysis Section Elements (New)
const trendAnalysisSection = document.getElementById('adminTrendAnalysisSection');
const trendClassCodeSelect = document.getElementById('trendClassCodeSelect');
const trendAnalysisDisplayArea = document.getElementById('trendAnalysisDisplayArea');
const trendAnalysisMessageArea = document.getElementById('trendAnalysisMessageArea');


const assignVisitModal = document.getElementById('assignVisitModal');
const assignVisitModalTitle = document.getElementById('assignVisitModalTitle');
const assignToUserSelect = document.getElementById('assignToUserSelect');
const assignUserSearchInput = document.getElementById('assignUserSearchInput');
const assignVisitModalMessageArea = document.getElementById('assignVisitModalMessageArea');

let currentClassCodeForAssignment = null;
let allUsersForAssignment = [];
let currentCharts = []; // Will hold all Chart.js instances (main and trend)

// --- Form Structure ---
const formStructure = [
    { id: 'class_stats', name: 'آمار کلاس', fields: [ { id: 'cs_total_students', label: 'تعداد کل دانش آموزان', type: 'number', required: true, placeholder: 'عدد وارد کنید' },{ id: 'cs_present_this_week', label: 'تعداد حاضرین این هفته', type: 'number', required: true, placeholder: 'عدد وارد کنید' },{ id: 'cs_absent_this_week', label: 'تعداد غائبین این هفته', type: 'number', required: true, placeholder: 'عدد وارد کنید' },{ id: 'cs_irregular_attendance', label: 'تعداد متربیانی که حضور منظم ندارند', type: 'number', required: false, placeholder: 'عدد (اختیاری)' },{ id: 'cs_latecomers_students', label: 'تعداد متأخرین (دانش‌آموزان)', type: 'number', required: false, placeholder: 'عدد (اختیاری)' },{ id: 'cs_teacher_attendance_status', label: 'وضعیت حضور مدرسین', type: 'checkbox', options: ['مدرس اول (راه انداز)', 'مدرس دوم (کمک)', 'مدرس سوم'], required: true },{ id: 'cs_teacher1_delay_minutes', label: 'میزان دقیقه تاخیر مدرس اول (در صورت حضور)', type: 'number', required: false, placeholder: 'عدد (دقایق)' },{ id: 'cs_teacher2_delay_minutes', label: 'میزان دقیقه تاخیر مدرس دوم (در صورت حضور)', type: 'number', required: false, placeholder: 'عدد (دقایق)' },{ id: 'cs_teacher3_delay_minutes', label: 'میزان دقیقه تاخیر مدرس سوم (در صورت حضور)', type: 'number', required: false, placeholder: 'عدد (اختیاری)' } ] },
    { id: 'skill_start_class', name: 'مهارت شروع کلاس', fields: [ { id: 'ssc_focus_creation', label: 'سامان دادن به افکار و ایجاد تمرکز', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true },{ id: 'ssc_attendance_method', label: 'شیوه انجام حضور و غیاب', type: 'checkbox', options: ['هوشمند', 'کتبی', 'شفاهی', 'عدم انجام'], required: true },{ id: 'ssc_attendance_list_shown', label: 'آیا لیست حضور و غیاب به نظر فراگیران رسید؟', type: 'radio', options: ['بله', 'خیر'], required: true },{ id: 'ssc_prev_week_review', label: 'آیا درباره مطالب هفته گذشته پرسش شد؟', type: 'radio', options: ['بله', 'خیر'], required: true },{ id: 'ssc_mind_prep_new_lesson', label: 'آماده سازی ذهن برای درس جدید', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true },{ id: 'ssc_motivation_creation', label: 'ایجاد علاقه و انگیزه برای مطلب جدید', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true },{ id: 'ssc_reaction_abs_delay_hw', label: 'واکنش به غیبت،تاخیر،انجام و عدم انجام تکالیف', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true } ] },
    { id: 'skill_alt_teaching', name: 'مهارت تدریس به گونه‌ای دیگر', fields: [ { id: 'sat_lesson_plan_exists', label: 'به نظر شما مدرس قبل از ورود به کلاس طرح درس تدوین کرده بود؟', type: 'radio', options: ['بله', 'خیر'], required: true },{ id: 'sat_student_participation_learning', label: 'مشارکت دانش آموزان به همراه معلم در فرایند پویای یادگیری', type: 'select', options: ['', 'کاملا', 'تا حدی', 'کم', 'اصلا'], required: true },{ id: 'sat_teaching_method_percentage', label: 'نحوه آموزش و برگزاری کلاس بدون درنظر گرفتن بخش بازی', type: 'percentage_rows', rows: [ { id: 'sat_group_based_percent', label: 'گروه محور' }, { id: 'sat_student_based_percent', label: 'فراگیر محور' },{ id: 'sat_tech_based_percent', label: 'تکنولوژی محور' }, { id: 'sat_teacher_based_percent', label: 'مدرس محور' } ], options: ['0', '20', '40', '60', '80', '100'], required: true },{ id: 'sat_modern_teaching_methods', label: 'استفاده از روش های تدریس مدرن', type: 'checkbox_with_other', options: ['ایفای نقش', 'ابزار تدریس', 'بازی های آموزشی', 'نداشتن'], otherOptionLabel: 'سایر (توضیح دهید)', required: true },{ id: 'sat_quran_recitation_method', label: 'روش قرائت قرآن در کلاس', type: 'checkbox', options: ['زنده', 'چندرسانه‌ای', 'همخوانی', 'تک خوانی', 'نداشتن'], required: true } ] },
    { id: 'skill_storytelling', name: 'مهارت استفاده از فنون قصه‌گویی', fields: [ { id: 'sst_voice_tone_appropriateness', label: 'آیا صدا و لحن مدرس، متناسب قصه گویی است؟', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true },{ id: 'sst_atmosphere_creation', label: 'فضاسازی مناسب و قابل درک برای فراگیران', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true },{ id: 'sst_story_suitability', label: 'آیا قصه، متناسب با زمان، فرهنگ، و سن مخاطبین انتخاب گردیده بود؟', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true },{ id: 'sst_storytelling_techniques', label: 'استفاده از شیوه ها و فنون قصه گویی', type: 'checkbox_with_other', options: ['قصه گویی در قالب نمایش', 'قصه گویی با تقلید صدا', 'قصه گویی ساده', 'قصه خوانی', 'پرده خوانی', 'قصه نداشتن'], otherOptionLabel: 'دیگر (توضیح دهید)', required: true } ] },
    { id: 'skill_class_control', name: 'مهارت کنترل کلاس و انضباط', fields: [ { id: 'scc_general_discipline', label: 'نظم و انضباط عمومی کلاس', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true },{ id: 'scc_behavior_guidance', label: 'هدایت و راهنمایی مناسب رفتار ها و گفتارهای فراگیران', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true },{ id: 'scc_teacher_gaze_distribution', label: 'تقسیم نگاه معلم بر متربیان', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true },{ id: 'scc_discipline_methods_voice_actions', label: 'استفاده از صدا و بیان، حرکات و روش های به کار گرفته شده از سوی معلم در جهت برقراری انضباط', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true },{ id: 'scc_punishment_methods_desc', label: 'روش های تنبیهی (توضیح دهید)', type: 'textarea', required: true, placeholder: 'روش‌های مشاهده شده را شرح دهید...' } ] },
    { id: 'skill_imam_mahdi_reminder', name: 'مهارت تذکر به امام عصر(عج)', fields: [ { id: 'simr_attention_to_imam', label: 'توجه داشتن به امام عصر(علیه السلام) در فضای کلی تدریس', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true },{ id: 'simr_encouragement_love_service', label: 'تشویق به محبت، ایجاد ارتباط و خدمتگزاری به امام حیّ با تاکید بر ناظر بودن ایشان', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true },{ id: 'simr_politeness_during_mention', label: 'ادب فراگیران در دعای اول جلسه یا موقع نام بردن از آن حضرت', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true },{ id: 'simr_advice_proximity_sins', label: 'توصیه ی کارهایی که باعث نزدیکی به آن حضرت میشود و نهی انجام گاناهان که باعث جدایی از یاد آن حضرت است', type: 'radio', options: ['بله', 'خیر'], required: true },{ id: 'simr_mahdaviat_topic_title', label: 'بیان موضوع خاصی از مهدویت؛ لطفا فقط عنوان را ذکر نمایید', type: 'text', required: true, placeholder: 'عنوان موضوع مهدوی مطرح شده' } ] },
    { id: 'content_evaluation', name: 'محتوا', fields: [ { id: 'ce_teacher_mastery', label: 'میزان تسلط مدرسین به مباحث', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true },{ id: 'ce_expression_comprehension', label: 'نحوه بیان و تفهیم مطالب', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true },{ id: 'ce_content_curriculum_match', label: 'میزان تطبیق با محتوای آموزشی', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true },{ id: 'ce_teaching_aids_usage', label: 'میزان استفاده از وسائل کمک آموزشی', type: 'select', options: ['', 'به اندازه', 'زیاد', 'کم', 'اصلا'], required: true },{ id: 'ce_teacher_student_language', label: 'نحوه بیان مدرس در ارتباط با متربی', type: 'radio', options: ['استفاده از زبان متربی', 'بیانی فراتر از فهم کودک'], required: true },{ id: 'ce_teacher_question_response', label: 'نحوه برخورد مدرس در مواجهه با پرسش', type: 'checkbox', options: ['مناسب', 'ارجاع به آینده', 'پاسخ کامل', 'عدم پاسخ'], required: true },{ id: 'ce_real_life_examples_religious', label: 'استفاده از مثال های روز و ملموس برای بیان آموزه های دینی - اعتقادی', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true },{ id: 'ce_class_sequence_mention_game_snack', label: 'سین کلاس(به ترتیب انجام) بازی و پذیرایی هم ذکر شود', type: 'textarea', required: true, placeholder: 'ترتیب فعالیت‌های کلاس را شرح دهید...' } ] },
    { id: 'game_and_refreshments', name: 'بازی و پذیرایی', fields: [ { id: 'gr_game_name', label: 'نام بازی', type: 'text', required: true, placeholder: 'نام بازی انجام شده' },{ id: 'gr_game_duration_minutes', label: 'مدت زمان بازی (دقیقه)', type: 'number', required: true, placeholder: 'به دقیقه وارد کنید' },{ id: 'gr_game_type', label: 'نوع بازی', type: 'checkbox_with_other', options: ['تحرکی', 'تمرکزی', 'آموزشی', 'آپارتمانی', 'حیاطی', 'با ابزار و وسیله', 'بدون ابزار و وسیله', 'گروه‌های جداگانه', 'فردی', 'جمعی همه باهم'], otherOptionLabel: 'دیگر (توضیح دهید)', required: true },{ id: 'gr_student_interest_game', label: 'میزان علاقه فراگیران به بازی', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true },{ id: 'gr_student_interaction', label: 'رابطه متقابل فراگیران', type: 'select', options: ['', 'عالی', 'خوب', 'متوسط', 'نامناسب'], required: true },{ id: 'gr_student_teacher_interaction', label: 'رابطه فراگیران با مدرسین', type: 'select', options: ['', 'عالی', 'خوب', 'متوسط', 'نامناسب'], required: true },{ id: 'gr_general_moral_traits_students', label: 'ویژگی های بارز اخلاقی عمومی فراگیران', type: 'textarea', required: true, placeholder: 'ویژگی‌های مشاهده شده را شرح دهید...' } ]},
    { id: 'general_environment', name: 'محیط کلاس', fields: [ { id: 'ge_hvac_ventilation', label: 'سیستم سرمایش و گرمایشی و تهویه مناسب', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true },{ id: 'ge_lighting', label: 'نورگیری مناسب فضای کلاس', type: 'select_score', options: ['', '1 (خیلی ضعیف)', '2 (ضعیف)', '3 (خوب)', '4 (عالی)'], required: true },{ id: 'ge_space_to_student_ratio', label: 'متناسب بودن فضای کلاس نسبت به تعداد فراگیران (ابعاد)', type: 'select', options: ['', 'بزرگ', 'متناسب', 'کمبود فضا'], required: true },{ id: 'ge_restroom_accessibility', label: 'محل سرویس بهداشتی', type: 'select', options: ['', 'در دسترس', 'دسترسی سخت', 'عدم دسترسی'], required: true },{ id: 'ge_seating_type', label: 'استفاده از صندلی', type: 'checkbox', options: ['بله', 'خیر', 'نیاز است', 'نیاز نیست'], required: true } ]},
    { id: 'general_facilities', name: 'امکانات', fields: [ { id: 'gf_whiteboard', label: 'تخت وایت برد', type: 'checkbox', options: ['متناسب', 'نامتناسب', 'کمبود', 'ناموجود'], required: true },{ id: 'gf_board_stand', label: 'پایه تخته', type: 'checkbox', options: ['متناسب', 'نامتناسب', 'کمبود', 'ناموجود'], required: true },{ id: 'gf_projector_tv', label: 'پرژکتور یا تلوزیون', type: 'checkbox', options: ['متناسب', 'نامتناسب', 'کمبود', 'ناموجود'], required: true },{ id: 'gf_speaker_system', label: 'اسپیکر یا باند', type: 'checkbox', options: ['متناسب', 'نامتناسب', 'کمبود', 'ناموجود'], required: true } ]}
];

// --- Helper Functions ---
function displayMessage(areaElement, message, isSuccess = true) {
    if (areaElement) {
        areaElement.innerHTML = message;
        areaElement.className = 'messageArea ' + (isSuccess ? 'message-success' : 'message-error');
        areaElement.classList.remove('hidden');
        setTimeout(() => {
            if (areaElement && !areaElement.classList.contains('persistent-message')) { // Don't auto-hide persistent messages
                areaElement.classList.add('hidden');
                areaElement.innerHTML = '';
            }
        }, 7000);
    } else {
        console.warn("Message area not found for:", message);
    }
}

async function callAppsScript(action, payload = {}) {
    let currentMessageAreaForCall = adminLoginMessageArea; 

    if(adminDashboardSection && !adminDashboardSection.classList.contains('hidden')) {
        const activeSection = adminMenu.querySelector('button.active');
        let activeSectionId = null;
        if (activeSection && activeSection.getAttribute('onclick')) {
            const match = activeSection.getAttribute('onclick').match(/'([^']+)'/);
            if (match) activeSectionId = match[1];
        }

        if (assignVisitModal && assignVisitModal.style.display === "flex") {
            currentMessageAreaForCall = assignVisitModalMessageArea;
        } else if (resultsViewerSection && !resultsViewerSection.classList.contains('hidden')) {
            currentMessageAreaForCall = viewResultsMessageArea;
        } else if (activeSectionId === 'adminVisitAssignmentsSection') {
            currentMessageAreaForCall = visitAssignmentsMessageArea;
        } else if (activeSectionId === 'adminUserManagementSection') {
            currentMessageAreaForCall = addUserMessageArea;
        } else if (activeSectionId === 'adminClassManagementSection') {
            currentMessageAreaForCall = addClassMessageArea;
        } else if (activeSectionId === 'adminAnalyticsSection') {
            currentMessageAreaForCall = analyticsMessageArea;
        } else if (activeSectionId === 'adminTrendAnalysisSection') { // New
            currentMessageAreaForCall = trendAnalysisMessageArea;
        } else if (activeSectionId === 'adminViewResultsSection') {
             currentMessageAreaForCall = viewResultsMessageArea;
        }
    }


    if (!WEB_APP_URL || WEB_APP_URL === 'YOUR_WEB_APP_URL_HERE' || !WEB_APP_URL.startsWith('https://script.google.com/macros/s/')) {
        if(currentMessageAreaForCall) displayMessage(currentMessageAreaForCall, 'خطا: URL وب اپلیکیشن به درستی تنظیم نشده است!', false);
        console.error('WEB_APP_URL is not configured correctly.');
        return { status: 'error', message: 'Web App URL not configured.' };
    }
    if(currentMessageAreaForCall && !currentMessageAreaForCall.classList.contains('persistent-message')) {
        currentMessageAreaForCall.classList.add('hidden');
        currentMessageAreaForCall.innerHTML = '';
    }


    const params = new URLSearchParams({action, ...payload});
    try {
        const response = await fetch(WEB_APP_URL, { method: 'POST', body: params });
        if (!response.ok) {
            const errorText = await response.text();
            console.error("Network error response text for action " + action + ":", errorText);
            throw new Error(`خطای شبکه: ${response.status} ${response.statusText}`);
        }
        const result = await response.json();
        // Log all successful backend responses for debugging if needed
        // console.log(`Response for action ${action}:`, result); 

         if (result.status === 'error') { 
             // Let specific errors pass through if they are handled by frontend logic (e.g., validation)
             const passThroughErrorActions = ['validateRegisteredUserLogin', 'validateUserAccess', 'validateAssignedVisitAccess', 'changeOwnPassword', 'toggleUserActiveStatus'];
             if (!passThroughErrorActions.includes(action) || (result.data && result.data.error)) { // If it's a true backend error or data.error is set
                throw new Error(result.message || 'خطای نامشخص از سمت سرور Apps Script');
             }
        }
        return result;
    } catch (error) {
        console.error('Error in callAppsScript for action "' + action + '":', error);
        if(currentMessageAreaForCall) displayMessage(currentMessageAreaForCall, 'خطا در ارتباط با سرور: ' + error.message, false);
        return { status: 'error', message: error.message, data: { error: true, message: error.message } }; // Ensure data.error for consistency
    }
}

// --- Admin Login and Session Management ---
async function sha256(str) {
    if (typeof crypto === 'undefined' || !crypto.subtle || !crypto.subtle.digest) {
        console.warn("crypto.subtle is not available (page not served over HTTPS or unsupported browser). Using a basic fallback for hashing (NOT SECURE).");
        let hash = 0;
        if (str.length === 0) return hash.toString(16);
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32bit integer
        }
        return Math.abs(hash).toString(16);
    }
    const buffer = new TextEncoder().encode(str);
    const hashBuffer = await crypto.subtle.digest('SHA-256', buffer);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    return hashHex;
}

async function handleAdminLogin() {
    const usernameInput = document.getElementById('adminUsername');
    const passwordInput = document.getElementById('adminPassword');
    if(!usernameInput || !passwordInput || !adminLoginMessageArea) return;

    const username = usernameInput.value.trim();
    const password = passwordInput.value;

    if (!username || !password) {
        displayMessage(adminLoginMessageArea, 'لطفاً نام کاربری و رمز عبور ادمین را وارد کنید.', false);
        return;
    }
    displayMessage(adminLoginMessageArea, 'در حال بررسی اطلاعات ادمین...', true);

    const inputPasswordHash = await sha256(password);

    if (username === ADMIN_USERNAME && inputPasswordHash === ADMIN_PASSWORD_HASH.toLowerCase()) { 
        sessionStorage.setItem('isAdminLoggedIn', 'true');
        if (adminLoginSection) adminLoginSection.classList.add('hidden');
        if (adminDashboardSection) adminDashboardSection.classList.remove('hidden');
        if (adminWelcomeMessage) adminWelcomeMessage.textContent = `خوش آمدید، ادمین گرامی!`;

        adminLoginMessageArea.classList.add('hidden');
        adminLoginMessageArea.innerHTML = '';

        usernameInput.value = ''; passwordInput.value = '';
        showAdminSectionContent('adminClassManagementSection');
        loadClassesForAdmin();  // This will also populate trendClassCodeSelect
        loadUsersForAdmin();
    } else {
        displayMessage(adminLoginMessageArea, 'نام کاربری یا رمز عبور ادمین نامعتبر است.', false);
        sessionStorage.removeItem('isAdminLoggedIn');
    }
}

function adminLogout() {
    sessionStorage.removeItem('isAdminLoggedIn');
    if (adminDashboardSection) adminDashboardSection.classList.add('hidden');
    if (adminLoginSection) adminLoginSection.classList.remove('hidden');
    if (document.getElementById('adminUsername')) document.getElementById('adminUsername').value = '';
    if (document.getElementById('adminPassword')) document.getElementById('adminPassword').value = '';

    const messageAreas = [
        'adminLoginMessageArea', 'addClassMessageArea', 'addUserMessageArea',
        'viewResultsMessageArea', 'analyticsMessageArea', 'assignVisitModalMessageArea',
        'visitAssignmentsMessageArea', 'trendAnalysisMessageArea' // Added
    ];
    messageAreas.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('hidden');
            el.innerHTML = ''; 
        }
    });

    if (resultsViewerSection) resultsViewerSection.classList.add('hidden');
    if (formDisplayForAdmin) formDisplayForAdmin.innerHTML = '';
    if (formDisplayForAdminNav) formDisplayForAdminNav.innerHTML = '';

    destroyCurrentCharts(); // Clears all charts
    if (analyticsDisplayArea) analyticsDisplayArea.innerHTML = '<p class="no-data-message"><em>لطفاً یک کلاس را برای مشاهده تحلیل انتخاب کنید.</em></p>';
    if (trendAnalysisDisplayArea) trendAnalysisDisplayArea.innerHTML = '<p class="no-data-message"><em>لطفاً یک کلاس (یا گزینه همه کلاس‌ها) را برای مشاهده روند پیشرفت انتخاب کنید.</em></p>';


    if(analyticsClassCodeSelect) analyticsClassCodeSelect.value = '';
    if(trendClassCodeSelect) trendClassCodeSelect.value = ''; // Reset trend select
    if(analyticsVisitPasswordInput) {
        analyticsVisitPasswordInput.value = '';
        if (visitPasswordControlGroupAnalytics) visitPasswordControlGroupAnalytics.classList.remove('hidden');
        analyticsVisitPasswordInput.disabled = false;
    }
}

function checkAdminLogin() {
    if (sessionStorage.getItem('isAdminLoggedIn') === 'true') {
        if (adminLoginSection) adminLoginSection.classList.add('hidden');
        if (adminDashboardSection) adminDashboardSection.classList.remove('hidden');
        if (adminWelcomeMessage) adminWelcomeMessage.textContent = `خوش آمدید، ادمین گرامی!`;
        return true;
    } else {
        if (adminDashboardSection) adminDashboardSection.classList.add('hidden');
        if (adminLoginSection) adminLoginSection.classList.remove('hidden');
        return false;
    }
}

function togglePasswordVisibility(inputId, buttonElement) {
    const passwordInput = document.getElementById(inputId);
    if (!passwordInput || !buttonElement) return;
    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        buttonElement.textContent = "مخفی";
    } else {
        passwordInput.type = "password";
        buttonElement.textContent = "نمایش";
    }
}

// --- Show/Hide Admin Dashboard Content Sections ---
function showAdminSectionContent(sectionIdToShow) {
    if (!adminContentArea || !adminMenu) { console.error("Admin content area or menu not found"); return; }
    
    Array.from(adminContentArea.children).forEach(child => {
        if (child.classList && child.classList.contains('admin-content-section')) { 
            child.classList.add('hidden');
        }
    });
    if (resultsViewerSection && sectionIdToShow !== 'resultsViewerSectionContent' && resultsViewerSection.id !== sectionIdToShow) { 
        resultsViewerSection.classList.add('hidden');
    }
    
    const targetAdminSection = document.getElementById(sectionIdToShow);
    if (targetAdminSection) {
        targetAdminSection.classList.remove('hidden');
         if (sectionIdToShow === 'adminVisitAssignmentsSection') {
            loadVisitAssignments();
        } else if (sectionIdToShow === 'adminClassManagementSection') {
            loadClassesForAdmin(); 
        } else if (sectionIdToShow === 'adminUserManagementSection') {
            loadUsersForAdmin(); 
        } else if (sectionIdToShow === 'adminAnalyticsSection') {
            // Clear previous results if any, prompt user to select
            if(analyticsDisplayArea) analyticsDisplayArea.innerHTML = '<p class="no-data-message"><em>لطفاً یک کلاس و در صورت نیاز پسورد بازدید را برای مشاهده تحلیل جزئی انتخاب کنید.</em></p>';
        } else if (sectionIdToShow === 'adminTrendAnalysisSection') {
            // Clear previous trend results if any, prompt user to select
             if(trendAnalysisDisplayArea) trendAnalysisDisplayArea.innerHTML = '<p class="no-data-message"><em>لطفاً یک کلاس (یا گزینه همه کلاس‌ها) را برای مشاهده روند پیشرفت انتخاب کنید.</em></p>';
        }
    } else {
        console.error(`Admin section with ID '${sectionIdToShow}' not found.`);
    }

    Array.from(adminMenu.children).forEach(button => {
        if (button.tagName === 'BUTTON') { 
            button.classList.remove('active');
            // Check if the button's onclick function call contains the sectionIdToShow
            const onclickAttr = button.getAttribute('onclick');
            if (onclickAttr && onclickAttr.includes(`'${sectionIdToShow}'`)) {
                button.classList.add('active');
            }
        }
    });

    // Clear view results specific input fields if not navigating to that section
    if (sectionIdToShow !== 'adminViewResultsSection' && sectionIdToShow !== 'resultsViewerSectionContent') {
         const vrcc = document.getElementById('viewResultsClassCode');
         const vrvp = document.getElementById('viewResultsVisitPassword');
         if(vrcc) vrcc.value = '';
         if(vrvp) vrvp.value = '';
         if(viewResultsMessageArea) {
             viewResultsMessageArea.classList.add('hidden');
             viewResultsMessageArea.innerHTML = '';
         }
    }
}

// --- Admin User Management ---
// ... (توابع addNewUserByAdmin, loadUsersForAdmin, handleToggleUserStatus, promptChangeUserPasswordByAdmin بدون تغییر نسبت به نسخه قبلی که ارسال شد)
async function handleToggleUserStatus(userId, currentIsActiveStatus) {
    if(!addUserMessageArea) return;
    const actionVerb = currentIsActiveStatus ? "غیرفعال" : "فعال";
    if (confirm(`آیا از ${actionVerb} کردن کاربر '${userId}' مطمئن هستید؟`)) {
        displayMessage(addUserMessageArea, `در حال ${actionVerb} کردن کاربر ${userId}...`, true);
        const result = await callAppsScript('toggleUserActiveStatus', { userId });

        if (result.status === 'success' && result.data && result.data.success === true) {
            displayMessage(addUserMessageArea, result.data.message, true);
            loadUsersForAdmin();
        } else if (result.status === 'success' && result.data && result.data.success === false) {
            displayMessage(addUserMessageArea, result.data.message, false);
        } else {
            displayMessage(addUserMessageArea, (result.data && result.data.message) || result.message || 'خطا در تغییر وضعیت کاربر.', false);
        }
    }
}
async function loadUsersForAdmin() {
    if (!usersListContainer) return;
    usersListContainer.innerHTML = '<p class="no-data-message">در حال بارگذاری لیست کاربران...</p>';
    const result = await callAppsScript('getUsersList', {onlyActive: 'false'}); 
    if (result.status === 'success' && Array.isArray(result.data)) {
        if (result.data.length === 0) {
            usersListContainer.innerHTML = '<p class="no-data-message">هنوز کاربری ثبت نشده است.</p>';
            return;
        }
        usersListContainer.innerHTML = '';
        result.data.forEach(user => {
            const userCard = document.createElement('div');
            userCard.className = 'user-card';

            const statusClass = user.isActive ? 'user-status-active' : 'user-status-inactive';
            const statusText = user.isActive ? 'فعال' : 'غیرفعال';
            const toggleButtonText = user.isActive ? "غیرفعال کردن" : "فعال کردن";
            const toggleButtonClass = user.isActive ? "btn-warning" : "btn-success";

            userCard.innerHTML = `
                <div class="user-card-info">
                    <span><strong>نام کاربری:</strong> ${user.userId}</span>
                    <span class="${statusClass}">${statusText}</span>
                </div>
                <div class="user-card-info">
                     <span><strong>نام نمایشی:</strong> ${user.displayName}</span>
                     <span><strong>آخرین ورود:</strong> ${user.lastLogin || 'هرگز'}</span>
                </div>
                <div class="user-card-info">
                    <span><strong>ایمیل:</strong> ${user.email || 'ثبت نشده'}</span>
                </div>
                <div class="user-card-actions">
                    <button class="btn-secondary btn-sm" onclick="promptChangeUserPasswordByAdmin('${user.userId}')">تغییر رمز</button>
                    <button class="${toggleButtonClass} btn-sm" onclick="handleToggleUserStatus('${user.userId}', ${user.isActive})">${toggleButtonText}</button>
                </div>
            `;
            usersListContainer.appendChild(userCard);
        });
    } else {
        usersListContainer.innerHTML = `<p class="no-data-message" style="color:red;">خطا در بارگذاری لیست کاربران: ${(result.data && result.data.message) || result.message || 'خطای نامشخص'}</p>`;
        if(addUserMessageArea) displayMessage(addUserMessageArea, `خطا در بارگذاری کاربران: ${(result.data && result.data.message) || result.message}`, false);
    }
}
async function addNewUserByAdmin() {
    const userIdInput = document.getElementById('newUserId');
    const displayNameInput = document.getElementById('newUserDisplayName');
    const passwordInput = document.getElementById('newUserPassword');
    const emailInput = document.getElementById('newUserEmail');

    if(!userIdInput || !displayNameInput || !passwordInput || !addUserMessageArea || !emailInput) return;

    const userId = userIdInput.value.trim();
    const displayName = displayNameInput.value.trim();
    const password = passwordInput.value;
    const email = emailInput.value.trim();


    if (!userId || !displayName || !password) {
        displayMessage(addUserMessageArea, 'نام کاربری، نام نمایشی و رمز عبور ضروری هستند.', false);
        return;
    }
    if (!/^[a-zA-Z0-9_.-]+$/.test(userId)) {
        displayMessage(addUserMessageArea, 'نام کاربری فقط می‌تواند شامل حروف انگلیسی، اعداد، نقطه، خط تیره و زیرخط باشد.', false);
        return;
    }
     if (password.length < 4) {
        displayMessage(addUserMessageArea, 'رمز عبور باید حداقل ۴ کاراکتر باشد.', false);
        return;
    }
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        displayMessage(addUserMessageArea, 'فرمت ایمیل وارد شده صحیح نیست.', false);
        return;
    }

    displayMessage(addUserMessageArea, 'در حال افزودن کاربر جدید...', true);
    const result = await callAppsScript('addNewUser', { userId, displayName, password, email, telegramChatId: null });

    if (result.status === 'success' && result.data && result.data.success === true) { 
        displayMessage(addUserMessageArea, result.data.message, true);
        userIdInput.value = '';
        displayNameInput.value = '';
        passwordInput.value = '';
        emailInput.value = '';
        loadUsersForAdmin();
    } else {
        displayMessage(addUserMessageArea, (result.data && result.data.message) || result.message || 'خطا در هنگام افزودن کاربر.', false);
    }
}
async function promptChangeUserPasswordByAdmin(userId) {
    const newPassword = prompt(`رمز عبور جدید را برای کاربر '${userId}' وارد کنید:`);
    if (newPassword === null) return; 
    if (newPassword.trim() === "") {
        displayMessage(addUserMessageArea, 'رمز عبور نمی‌تواند خالی باشد.', false);
        return;
    }
     if (newPassword.length < 4) {
        displayMessage(addUserMessageArea, 'رمز عبور باید حداقل ۴ کاراکتر باشد.', false);
        return;
    }

    displayMessage(addUserMessageArea, `در حال تغییر رمز عبور کاربر ${userId}...`, true);
    const result = await callAppsScript('changeUserPasswordByAdmin', { userId, newPassword });
    if (result.status === 'success' && result.data && result.data.success) {
        displayMessage(addUserMessageArea, result.data.message, true);
    } else {
        displayMessage(addUserMessageArea, (result.data && result.data.message) || result.message || 'خطا در تغییر رمز عبور.', false);
    }
}

// --- Admin Class & Visit Assignment Management ---
async function openAssignVisitModal(classCode, className) {
    currentClassCodeForAssignment = classCode;
    if (assignVisitModalTitle) assignVisitModalTitle.textContent = `صدور و تخصیص پسورد بازدید برای کلاس: ${className} (کد: ${classCode})`;
    if (assignToUserSelect) assignToUserSelect.innerHTML = '<option value="">-- بازدید عمومی (بدون کاربر خاص) --</option>';
    if (assignUserSearchInput) assignUserSearchInput.value = '';
    if (assignVisitModalMessageArea) assignVisitModalMessageArea.classList.add('hidden');

    allUsersForAssignment = [];
    const usersResult = await callAppsScript('getUsersList', { onlyActive: 'true' });
    if (usersResult.status === 'success' && Array.isArray(usersResult.data)) {
        allUsersForAssignment = usersResult.data;
        populateAssignUserDropdown(allUsersForAssignment);
    } else {
        console.error("Error loading users for assignment:", (usersResult.data && usersResult.data.message) || usersResult.message);
        if(assignVisitModalMessageArea) displayMessage(assignVisitModalMessageArea, 'خطا در بارگذاری لیست کاربران فعال برای تخصیص.', false);
    }
    if(assignVisitModal) assignVisitModal.style.display = "flex";
}
// populateAssignUserDropdown, filterAssignUserDropdown, closeAssignVisitModal, confirmGenerateVisitPassword (بدون تغییر نسبت به نسخه قبل)
function populateAssignUserDropdown(usersToDisplay) {
    if (!assignToUserSelect) return;
    assignToUserSelect.innerHTML = '<option value="">-- بازدید عمومی (بدون کاربر خاص) --</option>';
    usersToDisplay.forEach(user => {
        const option = document.createElement('option');
        option.value = user.userId;
        option.textContent = `${user.displayName} (${user.userId})`;
        assignToUserSelect.appendChild(option);
    });
}

function filterAssignUserDropdown() {
    if (!assignUserSearchInput || !allUsersForAssignment || !assignToUserSelect) return;
    const filterText = assignUserSearchInput.value.toLowerCase().trim();
    const filteredUsers = allUsersForAssignment.filter(user =>
        user.displayName.toLowerCase().includes(filterText) ||
        user.userId.toLowerCase().includes(filterText)
    );
    populateAssignUserDropdown(filteredUsers);
}

function closeAssignVisitModal() {
    if(assignVisitModal) assignVisitModal.style.display = "none";
    currentClassCodeForAssignment = null;
    if(assignVisitModalMessageArea) assignVisitModalMessageArea.classList.add('hidden');
    if(assignUserSearchInput) assignUserSearchInput.value = ''; 
}

async function confirmGenerateVisitPassword() {
    if (!currentClassCodeForAssignment) {
        if(assignVisitModalMessageArea) displayMessage(assignVisitModalMessageArea, 'خطای داخلی: کد کلاس برای صدور مشخص نشده است.', false);
        return;
    }
    const selectedUserID = assignToUserSelect ? assignToUserSelect.value : null;
    if(assignVisitModalMessageArea) displayMessage(assignVisitModalMessageArea, 'در حال صدور پسورد بازدید...', true);

    const result = await callAppsScript('generateNextVisitPassword', {
        classCode: currentClassCodeForAssignment,
        assignedToUserID: selectedUserID || null 
    });

    if (result.status === 'success' && result.data && result.data.newPassword !== undefined) {
        let message = `پسورد جدید <strong>${result.data.newPassword}</strong> برای کلاس ${result.data.classCode} (${result.data.className || 'نامشخص'}) صادر شد.`;
        if (result.data.assignedTo && assignToUserSelect) {
            const userDetails = allUsersForAssignment.find(u => u.userId === result.data.assignedTo);
            const displayName = userDetails ? userDetails.displayName : result.data.assignedTo;
            message += ` و به کاربر <strong>${displayName} (${result.data.assignedTo})</strong> تخصیص یافت.`;
        } else {
            message += " این یک بازدید عمومی است.";
        }
        if(assignVisitModalMessageArea) displayMessage(assignVisitModalMessageArea, message, true);
        loadClassesForAdmin(); 
        const visitAssignmentsSection = document.getElementById('adminVisitAssignmentsSection');
        if (visitAssignmentsSection && !visitAssignmentsSection.classList.contains('hidden')) {
            loadVisitAssignments(); 
        }
        setTimeout(closeAssignVisitModal, 4000); 
    } else {
         if(assignVisitModalMessageArea) displayMessage(assignVisitModalMessageArea, (result.data && result.data.message) || result.message || 'خطا در تولید پسورد جدید.', false);
    }
}


// فایل: admin.js
// ... (کدهای قبلی تا قبل از تابع loadClassesForAdmin) ...

async function loadClassesForAdmin() {
    if (!classListDiv) {
        console.error("Element with ID 'classList' not found.");
        // return; // اگر می‌خواهید در صورت نبود المان، کلاً متوقف شود
    }
    if (classListDiv) classListDiv.innerHTML = '<p class="no-data-message">در حال بارگذاری لیست کلاس‌ها...</p>';

    // اطمینان از وجود المان‌های select
    if (!analyticsClassCodeSelect) console.error("Element with ID 'analyticsClassCodeSelect' not found.");
    if (!trendClassCodeSelect) console.error("Element with ID 'trendClassCodeSelect' not found.");

    const result = await callAppsScript('getClassesList');

    // پاک کردن و آماده‌سازی لیست‌های کشویی
    const selectsToPopulate = [analyticsClassCodeSelect, trendClassCodeSelect];
    selectsToPopulate.forEach(currentSelect => {
        if (currentSelect) {
            const currentSelectedValue = currentSelect.value; // حفظ مقدار انتخاب شده قبلی
            currentSelect.innerHTML = '<option value="">-- انتخاب کلاس --</option>'; // گزینه پیش‌فرض

            if (currentSelect.id === 'analyticsClassCodeSelect') {
                const allOpt = document.createElement('option');
                allOpt.value = "ALL_CLASSES";
                allOpt.textContent = "--- تحلیل کلی همه کلاس‌ها ---";
                currentSelect.appendChild(allOpt);
            } else if (currentSelect.id === 'trendClassCodeSelect') {
                const allTrendOpt = document.createElement('option');
                allTrendOpt.value = "ALL_CLASSES_TREND";
                allTrendOpt.textContent = "--- روند کلی همه کلاس‌ها (تجمیع ماهانه) ---";
                currentSelect.appendChild(allTrendOpt);
            }
            // بازیابی مقدار انتخاب شده قبلی اگر هنوز معتبر است
            if (currentSelectedValue) {
                // Check if the previously selected value still exists as an option after repopulating
                // This logic needs to be after options are added from 'result.data'
            }
        }
    });

    if (result.status === 'success' && Array.isArray(result.data)) {
        if (result.data.length === 0) {
            if (classListDiv) classListDiv.innerHTML = '<p class="no-data-message">هنوز کلاسی ثبت نشده است.</p>';
        } else {
            if (classListDiv) classListDiv.innerHTML = ''; // پاک کردن پیام "در حال بارگذاری" از card list

            const sortedClasses = result.data.sort((a, b) => {
                const numA = parseInt(String(a.classCode).match(/\d+/g)?.join('')) || 0;
                const numB = parseInt(String(b.classCode).match(/\d+/g)?.join('')) || 0;
                if (numA !== numB) return numA - numB;
                return (a.className || "").localeCompare(b.className || "", 'fa');
            });

            sortedClasses.forEach(cls => {
                // اضافه کردن کارت کلاس به classListDiv
                if (classListDiv) {
                    const classDiv = document.createElement('div');
                    classDiv.className = 'class-item';
                    classDiv.innerHTML = `<h3>${cls.className || 'نام کلاس نامشخص'} (کد: ${cls.classCode || 'کد نامشخص'})</h3>
                                        <p>آخرین پسورد بازدید صادر شده: <strong>${cls.lastPassword === undefined ? 'N/A' : cls.lastPassword}</strong></p>
                                        <div class="class-buttons">
                                            <button onclick="openAssignVisitModal('${cls.classCode}', '${String(cls.className || "").replace(/'/g, "\\'")}')" class="btn-success">صدور/تخصیص بازدید</button>
                                            <button onclick="deleteClass('${cls.classCode}')" class="btn-danger">حذف کلاس</button>
                                        </div>`;
                    classListDiv.appendChild(classDiv);
                }

                // اضافه کردن گزینه به هر دو لیست کشویی
                selectsToPopulate.forEach(currentSelect => {
                    if (currentSelect) {
                        const option = document.createElement('option');
                        option.value = cls.classCode;
                        option.textContent = `${cls.className} (کد: ${cls.classCode})`;
                        currentSelect.appendChild(option);
                    }
                });
            });
        }
    } else {
        const errorMsg = (result.data && result.data.message) || result.message || 'خطای نامشخص';
        if (classListDiv) classListDiv.innerHTML = `<p class="no-data-message" style="color:red;">خطا در بارگذاری لیست کلاس‌ها: ${errorMsg}</p>`;
        if (addClassMessageArea) displayMessage(addClassMessageArea, `خطا در بارگذاری کلاس‌ها: ${errorMsg}`, false);
        // در صورت خطا، لیست‌های کشویی هم پیام خطا را نشان دهند
        selectsToPopulate.forEach(currentSelect => {
            if (currentSelect) {
                 currentSelect.innerHTML = '<option value="">خطا در بارگذاری</option>';
                 if (currentSelect.id === 'analyticsClassCodeSelect') {
                    const allOpt = document.createElement('option');
                    allOpt.value = "ALL_CLASSES";
                    allOpt.textContent = "--- تحلیل کلی همه کلاس‌ها ---";
                    currentSelect.appendChild(allOpt);
                } else if (currentSelect.id === 'trendClassCodeSelect') {
                    const allTrendOpt = document.createElement('option');
                    allTrendOpt.value = "ALL_CLASSES_TREND";
                    allTrendOpt.textContent = "--- روند کلی همه کلاس‌ها (تجمیع ماهانه) ---";
                    currentSelect.appendChild(allTrendOpt);
                }
            }
        });
    }

    // بازیابی مقدار انتخاب شده برای هر select پس از پر شدن گزینه‌ها
    selectsToPopulate.forEach(currentSelect => {
        if (currentSelect) {
            const currentSelectedValue = currentSelect.getAttribute('data-prev-value'); // بازیابی از data attribute
            if (currentSelectedValue && Array.from(currentSelect.options).some(opt => opt.value === currentSelectedValue)) {
                currentSelect.value = currentSelectedValue;
            }
            currentSelect.removeAttribute('data-prev-value'); // پاک کردن attribute

            // اضافه کردن event listener برای analyticsClassCodeSelect اگر هنوز اضافه نشده
            if (currentSelect.id === 'analyticsClassCodeSelect' && !currentSelect.getAttribute('listenerAttached')) {
                currentSelect.addEventListener('change', function() {
                    const visitPasswordGroup = document.getElementById('visitPasswordControlGroupAnalytics');
                    const visitPasswordInput = document.getElementById('analyticsVisitPassword');
                    if (this.value === 'ALL_CLASSES') {
                        if (visitPasswordInput) { visitPasswordInput.value = ''; visitPasswordInput.disabled = true; }
                        if (visitPasswordGroup) visitPasswordGroup.classList.add('hidden');
                    } else {
                        if (visitPasswordInput) visitPasswordInput.disabled = false;
                        if (visitPasswordGroup) visitPasswordGroup.classList.remove('hidden');
                    }
                    // پاک کردن نتایج تحلیل جزئی هنگام تغییر کلاس
                    if (analyticsDisplayArea) analyticsDisplayArea.innerHTML = '<p class="no-data-message"><em>لطفاً برای مشاهده تحلیل جزئی، دکمه "بارگذاری تحلیل جزئی" را بزنید.</em></p>';

                });
                currentSelect.setAttribute('listenerAttached', 'true');
                currentSelect.dispatchEvent(new Event('change')); // فراخوانی اولیه
            }
            // اضافه کردن event listener برای trendClassCodeSelect اگر هنوز اضافه نشده
            if (currentSelect.id === 'trendClassCodeSelect' && !currentSelect.getAttribute('listenerAttachedTrend')) {
                 currentSelect.addEventListener('change', function() {
                    // پاک کردن نتایج تحلیل روند هنگام تغییر کلاس
                    if (trendAnalysisDisplayArea) trendAnalysisDisplayArea.innerHTML = '<p class="no-data-message"><em>لطفاً برای مشاهده روند، دکمه "نمایش نمودارهای روند" را بزنید.</em></p>';
                 });
                 currentSelect.setAttribute('listenerAttachedTrend', 'true');
            }
        }
    });
}


async function addNewClass() {
    const classCodeInput = document.getElementById('newClassCode');
    const classNameInput = document.getElementById('newClassName');
    if (!classCodeInput || !classNameInput || !addClassMessageArea) return;
    const classCode = classCodeInput.value.trim();
    const className = classNameInput.value.trim();
    if (!classCode || !className) {
        displayMessage(addClassMessageArea, 'کد کلاس و نام کلاس نمی‌توانند خالی باشند.', false);
        return;
    }
    displayMessage(addClassMessageArea, 'در حال افزودن کلاس جدید...', true);
    const result = await callAppsScript('addNewClass', { classCode, className });
    if (result.status === 'success' && result.data && result.data.message && result.data.error !== true) {
        displayMessage(addClassMessageArea, result.data.message, true);
        classCodeInput.value = '';
        classNameInput.value = '';
        loadClassesForAdmin();
    } else {
        displayMessage(addClassMessageArea, (result.data && result.data.message) || result.message || 'خطا در هنگام افزودن کلاس.', false);
    }
}

async function deleteClass(classCode) {
    if (!addClassMessageArea) return;
    if (confirm(`آیا از حذف کلاس با کد "${classCode}" و تمام بازدیدهای تخصیص یافته مرتبط مطمئن هستید؟\n(فرم‌های ارسالی حذف نخواهند شد). این عمل قابل بازگشت نیست.`)) {
        displayMessage(addClassMessageArea, `در حال حذف کلاس ${classCode}...`, true);
        const result = await callAppsScript('deleteClass', { classCode });
        if (result.status === 'success' && result.data && result.data.message && result.data.error !== true) {
            displayMessage(addClassMessageArea, result.data.message, true);
            loadClassesForAdmin(); // This will re-populate all select dropdowns
            // Clear analytics display if the deleted class was selected
            if (analyticsClassCodeSelect && analyticsClassCodeSelect.value === classCode) {
                analyticsClassCodeSelect.value = "";
                if(analyticsDisplayArea) analyticsDisplayArea.innerHTML = '<p class="no-data-message"><em>لطفاً یک کلاس را برای مشاهده تحلیل انتخاب کنید.</em></p>';
            }
            if (trendClassCodeSelect && trendClassCodeSelect.value === classCode) {
                trendClassCodeSelect.value = "";
                 if(trendAnalysisDisplayArea) trendAnalysisDisplayArea.innerHTML = '<p class="no-data-message"><em>لطفاً یک کلاس (یا گزینه همه کلاس‌ها) را برای مشاهده روند پیشرفت انتخاب کنید.</em></p>';
            }

            const visitAssignmentsSection = document.getElementById('adminVisitAssignmentsSection');
            if (visitAssignmentsSection && !visitAssignmentsSection.classList.contains('hidden')) {
                 loadVisitAssignments();
            }
        } else {
            displayMessage(addClassMessageArea, (result.data && result.data.message) || result.message || `خطا در حذف کلاس ${classCode}.`, false);
        }
    }
}

async function loadVisitAssignments() {
    if (!visitAssignmentsTableContainer || !visitAssignmentsMessageArea) return;
    visitAssignmentsTableContainer.innerHTML = '<p class="no-data-message">در حال بارگذاری لیست تخصیص بازدیدها...</p>';
    visitAssignmentsMessageArea.classList.add('hidden');

    const result = await callAppsScript('getVisitAssignmentsList');

    if (result.status === 'success' && Array.isArray(result.data)) {
        if (result.data.length === 0) {
            visitAssignmentsTableContainer.innerHTML = '<p class="no-data-message">هیچ بازدید تخصیص داده شده‌ای یافت نشد.</p>';
        } else {
            let tableHTML = '<table id="visitAssignmentsTable"><thead><tr>' +
                            '<th>کد کلاس</th><th>نام کلاس</th><th>پسورد بازدید</th><th>تخصیص به</th><th>وضعیت</th><th>تاریخ ایجاد</th><th>تاریخ تکمیل</th>' +
                            '</tr></thead><tbody>';
            result.data.forEach(assignment => { 
                const statusClass = assignment.isSubmitted ? 'status-completed' : 'status-pending';
                const statusText = assignment.isSubmitted ? 'تکمیل شده' : 'منتظر تکمیل';
                const assignedToText = assignment.assignedToUserDisplayName && assignment.assignedToUserDisplayName !== "کاربر یافت نشد"
                                        ? `${assignment.assignedToUserDisplayName} (${assignment.assignedToUserID || ''})`
                                        : (assignment.assignedToUserID ? `کاربر: ${assignment.assignedToUserID}` : 'عمومی');
                const createdDateFormatted = assignment.createdTimestamp ? new Date(assignment.createdTimestamp).toLocaleString('fa-IR', {year: 'numeric', month: '2-digit', day: '2-digit', hour:'2-digit', minute:'2-digit', timeZone: 'Asia/Tehran'}) : 'N/A';
                const submissionDateFormatted = assignment.submissionTimestamp && assignment.isSubmitted ? new Date(assignment.submissionTimestamp).toLocaleString('fa-IR', {year: 'numeric', month: '2-digit', day: '2-digit', hour:'2-digit', minute:'2-digit', timeZone: 'Asia/Tehran'}) : '-';

                tableHTML += `<tr>
                                <td>${assignment.classCode || 'N/A'}</td>
                                <td>${assignment.className || 'N/A'}</td>
                                <td>${assignment.visitPassword || 'N/A'}</td>
                                <td>${assignedToText}</td>
                                <td class="${statusClass}">${statusText}</td>
                                <td>${createdDateFormatted}</td>
                                <td>${submissionDateFormatted}</td>
                              </tr>`;
            });
            tableHTML += '</tbody></table>';
            visitAssignmentsTableContainer.innerHTML = tableHTML;
        }
    } else {
        const errorMsg = (result.data && result.data.message) || result.message || 'خطای نامشخص';
        visitAssignmentsTableContainer.innerHTML = `<p class="no-data-message" style="color:red;">خطا در بارگذاری لیست تخصیص بازدیدها: ${errorMsg}</p>`;
        displayMessage(visitAssignmentsMessageArea, `خطا: ${errorMsg}`, false);
    }
}

// --- Admin Form Viewing ---
// ... (توابع viewResults, hideResultsViewer, loadUserFormStructureForAdminView, showFormSectionForAdminView, populateAndDisableFormForAdmin, generatePdfFromView بدون تغییر نسبت به نسخه قبل)
async function viewResults() {
    if (!viewResultsMessageArea || !formDisplayForAdmin || !adminFormViewInfo || !downloadPdfButton || !resultsViewerSection) return;
    viewResultsMessageArea.classList.add('hidden');
    formDisplayForAdmin.innerHTML = '';
    if(formDisplayForAdminNav) formDisplayForAdminNav.innerHTML = '';

    const classCodeInput = document.getElementById('viewResultsClassCode');
    const visitPasswordInput = document.getElementById('viewResultsVisitPassword');
    if(!classCodeInput || !visitPasswordInput) return;

    const classCode = classCodeInput.value.trim();
    const visitPassword = visitPasswordInput.value.trim();
    sessionStorage.setItem('currentViewingClassCode', classCode); 
    sessionStorage.setItem('currentViewingVisitPassword', visitPassword);

    if (!classCode || !visitPassword) {
        displayMessage(viewResultsMessageArea, 'لطفاً کد کلاس و پسورد بازدید را برای مشاهده نتایج وارد کنید.', false);
        return;
    }
    displayMessage(viewResultsMessageArea, 'در حال دریافت اطلاعات بازدید...', true);
    const result = await callAppsScript('getSubmittedFormData', { classCode, visitPassword });
    if (result.status === 'success' && result.data && !result.data.notFound && !result.data.error) {
        displayMessage(viewResultsMessageArea, 'اطلاعات با موفقیت دریافت شد.', true);
        if(adminContentArea) {
            Array.from(adminContentArea.children).forEach(child => {
                if (child.classList && child.classList.contains('admin-content-section')) child.classList.add('hidden');
            });
        }
        resultsViewerSection.classList.remove('hidden');

        loadUserFormStructureForAdminView(formDisplayForAdmin, "formDisplayForAdminNav"); 
        populateAndDisableFormForAdmin(result.data, formDisplayForAdmin); 
        if(adminFormViewInfo) adminFormViewInfo.textContent = `فرم بازدید برای کلاس: ${result.data.ClassName_Submitted_For || 'نامشخص'} (کد: ${result.data.Class_Code_Used}، بازدید: ${result.data.Visit_Password_Used})`;
        if(resultsViewerTitle) resultsViewerTitle.textContent = `فرم تکمیل شده بازدید: ${result.data.ClassName_Submitted_For || classCode} - ${visitPassword}`;
        if(downloadPdfButton) downloadPdfButton.classList.remove('hidden');
    } else {
        displayMessage(viewResultsMessageArea, (result.data && result.data.message) || result.message || 'خطایی در دریافت اطلاعات رخ داد یا فرمی یافت نشد.', false);
         if(resultsViewerSection) resultsViewerSection.classList.add('hidden'); 
    }
}

function hideResultsViewer() {
    if (!resultsViewerSection || !formDisplayForAdmin || !downloadPdfButton || !adminMenu) return;
    resultsViewerSection.classList.add('hidden');
    formDisplayForAdmin.innerHTML = '';
    if(formDisplayForAdminNav) formDisplayForAdminNav.innerHTML = ''; 
    downloadPdfButton.classList.add('hidden');
    
    const activeAdminMenuButton = adminMenu.querySelector('button.active');
    let targetSection = 'adminClassManagementSection'; // Default
    if (activeAdminMenuButton) {
        const onclickAttr = activeAdminMenuButton.getAttribute('onclick');
        if (onclickAttr) {
            const sectionIdMatch = onclickAttr.match(/'([^']+)'/);
            if (sectionIdMatch && sectionIdMatch[1]) {
                targetSection = sectionIdMatch[1];
            }
        }
    }
    showAdminSectionContent(targetSection); 

    const vrcInput = document.getElementById('viewResultsClassCode');
    const vrvpInput = document.getElementById('viewResultsVisitPassword');
    if(vrcInput) vrcInput.value = '';
    if(vrvpInput) vrvpInput.value = '';
    if(viewResultsMessageArea) viewResultsMessageArea.classList.add('hidden');
}

function loadUserFormStructureForAdminView(containerToPopulate, navContainerId = 'formDisplayForAdminNav') {
    const navContainer = document.getElementById(navContainerId);
    const sectionsContainer = containerToPopulate;
    if (!sectionsContainer) { console.error("Form container not found for admin view."); return; }
    if (navContainer) navContainer.innerHTML = '';
    sectionsContainer.innerHTML = '';
    if (navContainer) navContainer.classList.remove('hidden'); 

    formStructure.forEach((section, index) => {
        if (navContainer) {
            const navButton = document.createElement('button');
            navButton.textContent = section.name;
            navButton.type = "button";
            navButton.onclick = () => showFormSectionForAdminView(section.id, navContainerId, sectionsContainer.id);
            if (index === 0) navButton.classList.add('active');
            navContainer.appendChild(navButton);
        }
        const sectionDiv = document.createElement('div');
        sectionDiv.id = `review-section-${section.id}`; 
        sectionDiv.className = 'form-section'; 
        if (index !== 0) sectionDiv.classList.add('hidden'); 
        else sectionDiv.classList.remove('hidden');

        sectionDiv.innerHTML = `<h4>${section.name}</h4>`;
        let currentSubSectionTitle = null;
        if (!section.fields || section.fields.length === 0) {
            sectionDiv.innerHTML += '<p><em>هنوز سوالی برای این بخش تعریف نشده است.</em></p>';
        } else {
            section.fields.forEach(field => {
                if (field.subSectionTitle && field.subSectionTitle !== currentSubSectionTitle) {
                    currentSubSectionTitle = field.subSectionTitle;
                    const subTitleEl = document.createElement('h5');
                    subTitleEl.className = 'sub-section-title';
                    subTitleEl.textContent = currentSubSectionTitle;
                    sectionDiv.appendChild(subTitleEl);
                }
                const fieldGroup = document.createElement('div');
                fieldGroup.className = 'form-field-group';
                const label = document.createElement('label');
                label.htmlFor = field.id + "_view"; 
                label.textContent = field.label; 
                fieldGroup.appendChild(label);

                if (field.type === 'textarea') {
                    const el = document.createElement('textarea');
                    el.id = field.id + "_view"; el.name = field.id + "_view";
                    if(field.placeholder) el.placeholder = field.placeholder;
                    el.disabled = true; el.readOnly = true; 
                    fieldGroup.appendChild(el);
                } else if (field.type === 'select' || field.type === 'select_score') {
                    const el = document.createElement('select');
                    el.id = field.id + "_view"; el.name = field.id + "_view";
                    el.disabled = true;
                    (field.options || []).forEach((optText,optIdx) => {
                        const option = document.createElement('option');
                        option.value = (field.type === 'select_score' && optText.includes('(')) ? optText.match(/\d+/)[0] : ( (optText === '--- انتخاب کنید ---' || optText === '') ? '' : optText );
                        option.textContent = optText || '--- انتخاب کنید ---';
                         if(optText === '' && optIdx === 0) { option.disabled = true; option.selected = true;}
                        el.appendChild(option);
                    });
                    fieldGroup.appendChild(el);
                } else if (field.type === 'radio' || field.type === 'checkbox') {
                    const inputGroupDiv = document.createElement('div');
                    inputGroupDiv.className = 'form-check-group';
                    (field.options || []).forEach(optValue => {
                        const wrapperDiv = document.createElement('div');
                        const input = document.createElement('input'); input.type = field.type;
                        input.id = `${field.id}_${optValue.replace(/\s+/g, '').replace(/[()]/g, '')}_view`; input.name = `${field.id}_view`; input.value = optValue; 
                        input.className = 'form-check-input';
                        input.disabled = true;
                        const optLabel = document.createElement('label'); optLabel.htmlFor = input.id; optLabel.textContent = optValue; optLabel.className = 'form-check-label';
                        wrapperDiv.appendChild(input); wrapperDiv.appendChild(optLabel); inputGroupDiv.appendChild(wrapperDiv);
                    });
                    fieldGroup.appendChild(inputGroupDiv);
                } else if (field.type === 'percentage_rows') {
                    field.rows.forEach(row => {
                        const rowDiv = document.createElement('div'); rowDiv.style.display = 'flex'; rowDiv.style.alignItems = 'center'; rowDiv.style.marginBottom = '0.5rem';
                        const rowLabel = document.createElement('label'); rowLabel.htmlFor = row.id + "_view"; rowLabel.textContent = row.label + ": "; rowLabel.style.width = '150px'; rowLabel.style.marginRight = '10px'; rowLabel.style.flexShrink = '0';
                        rowDiv.appendChild(rowLabel);
                        const selectEl = document.createElement('select'); selectEl.id = row.id + "_view"; selectEl.name = row.id + "_view";
                        selectEl.disabled = true;
                        (field.options || []).forEach(opt => { const optionEl = document.createElement('option'); optionEl.value = opt; optionEl.textContent = opt + '%'; selectEl.appendChild(optionEl); });
                        selectEl.style.flexGrow = '1'; rowDiv.appendChild(selectEl); fieldGroup.appendChild(rowDiv);
                    });
                } else if (field.type === 'checkbox_with_other') {
                    const inputGroupDiv = document.createElement('div'); inputGroupDiv.className = 'form-check-group';
                    (field.options || []).forEach(optValue => {
                        const wrapperDiv = document.createElement('div'); const input = document.createElement('input'); input.type = 'checkbox';
                        input.id = `${field.id}_${optValue.replace(/\s+/g, '').replace(/[()]/g, '')}_view`; input.name = `${field.id}_view`; input.value = optValue; input.className = 'form-check-input';
                        input.disabled = true;
                        const optLabel = document.createElement('label'); optLabel.htmlFor = input.id; optLabel.textContent = optValue; optLabel.className = 'form-check-label';
                        wrapperDiv.appendChild(input); wrapperDiv.appendChild(optLabel); inputGroupDiv.appendChild(wrapperDiv);
                    });
                    const otherWrapperDiv = document.createElement('div'); const otherInput = document.createElement('input'); otherInput.type = 'checkbox';
                    otherInput.id = `${field.id}_other_checkbox_view`; otherInput.name = `${field.id}_view`; otherInput.value = field.otherOptionLabel || 'سایر'; otherInput.className = 'form-check-input';
                    otherInput.disabled = true;
                    const otherLabel = document.createElement('label'); otherLabel.htmlFor = otherInput.id; otherLabel.textContent = field.otherOptionLabel || 'سایر'; otherLabel.className = 'form-check-label';
                    otherWrapperDiv.appendChild(otherInput); otherWrapperDiv.appendChild(otherLabel);
                    const otherTextInput = document.createElement('input'); otherTextInput.type = 'text'; otherTextInput.id = `${field.id}_other_text_view`; otherTextInput.name = `${field.id}_other_text_view`;
                    otherTextInput.placeholder = 'توضیحات سایر'; otherTextInput.classList.add('sub-input-text'); 
                    otherTextInput.disabled = true; otherTextInput.readOnly = true;
                    otherTextInput.classList.add('hidden'); 
                    otherWrapperDiv.appendChild(otherTextInput); inputGroupDiv.appendChild(otherWrapperDiv); fieldGroup.appendChild(inputGroupDiv);
                } else if (field.type === 'number' || field.type === 'date' || field.type === 'time' || field.type === 'text'){
                    const el = document.createElement('input'); el.type = field.type; el.id = field.id + "_view"; el.name = field.id + "_view";
                    if(field.placeholder) el.placeholder = field.placeholder;
                    el.disabled = true; el.readOnly = true;
                    if (field.type === 'number') { el.min = "0"; } fieldGroup.appendChild(el);
                }
                sectionDiv.appendChild(fieldGroup);
            });
        }
        sectionsContainer.appendChild(sectionDiv);
    });
    if (formStructure.length > 0 && navContainer) {
        showFormSectionForAdminView(formStructure[0].id, navContainerId, sectionsContainer.id);
    }
}

function showFormSectionForAdminView(sectionIdToShow, navId = 'formDisplayForAdminNav', containerId = 'formDisplayForAdmin') {
    const activeNavContainer = document.getElementById(navId);
    const activeSectionsContainer = document.getElementById(containerId);
    if (!activeSectionsContainer || !resultsViewerSection) return; // Ensure resultsViewerSection exists for scrolling context

    if(activeNavContainer) activeNavContainer.querySelectorAll('button').forEach(btn => btn.classList.remove('active'));
    activeSectionsContainer.querySelectorAll('.form-section').forEach(sec => sec.classList.add('hidden'));
    
    const targetSectionInfo = formStructure.find(s => s.id === sectionIdToShow);
    if (targetSectionInfo) {
        if(activeNavContainer){
            const activeButton = Array.from(activeNavContainer.querySelectorAll('button')).find(btn => btn.textContent === targetSectionInfo.name);
            if (activeButton) activeButton.classList.add('active');
        }
        const sectionIdPrefix = 'review-'; 
        const sectionToShowEl = document.getElementById(`${sectionIdPrefix}section-${sectionIdToShow}`);
        if (sectionToShowEl && activeSectionsContainer && activeSectionsContainer.contains(sectionToShowEl)) {
            sectionToShowEl.classList.remove('hidden');
            
            // Scroll logic for the results viewer section
            if (activeNavContainer && activeNavContainer.offsetParent !== null && resultsViewerSection.classList.contains('results-viewer-container')) { 
                const navBarHeight = activeNavContainer.offsetHeight;
                const viewerHeader = resultsViewerSection.querySelector('.viewer-header');
                const viewerHeaderHeight = viewerHeader ? viewerHeader.offsetHeight : 0;
                const stickyOffset = navBarHeight + viewerHeaderHeight + 15; 
                
                const elementTopRelativeToViewport = sectionToShowEl.getBoundingClientRect().top;
                const scrollableParentTopRelativeToViewport = resultsViewerSection.getBoundingClientRect().top;
                const currentScrollOfParent = resultsViewerSection.scrollTop;

                const desiredScrollTop = currentScrollOfParent + (elementTopRelativeToViewport - scrollableParentTopRelativeToViewport) - stickyOffset;
                
                resultsViewerSection.scrollTo({
                    top: desiredScrollTop,
                    behavior: 'smooth'
                });
            }
        }
    }
}

function populateAndDisableFormForAdmin(formData, containerElement) {
    formStructure.forEach(section => {
        if (section.fields) {
            section.fields.forEach(field => {
                let valueFromSheet = formData[field.id];
                let otherTextValue = field.type === 'checkbox_with_other' ? formData[`${field.id}_other_text`] : null;
                
                const viewFieldIdSuffix = "_view";

                if (field.type === 'percentage_rows') {
                    field.rows.forEach(row => {
                        const element = containerElement.querySelector(`#${row.id + viewFieldIdSuffix}`);
                        if (element && formData[row.id] !== undefined) {
                            element.value = formData[row.id];
                        }
                    });
                } else {
                    const elementName = field.id + viewFieldIdSuffix;
                    const elements = containerElement.querySelectorAll(`[name="${elementName}"]`);

                    if (elements.length > 0 && (field.type === 'radio' || field.type === 'checkbox' || field.type === 'checkbox_with_other')) {
                        elements.forEach(el => {
                            if(el.type === 'radio' && el.value === valueFromSheet) el.checked = true;
                            else if (el.type === 'checkbox') {
                                const valuesArray = valueFromSheet ? String(valueFromSheet).split(', ').map(s => s.trim()) : [];
                                const otherCheckboxId = `${field.id}_other_checkbox_view`;
                                const currentElValue = el.value.trim();
                                
                                if (valuesArray.includes(currentElValue) || 
                                    (el.id === otherCheckboxId && otherTextValue && (valuesArray.includes(otherTextValue.trim()) || currentElValue === (field.otherOptionLabel || 'سایر').trim() ))
                                ) {
                                    el.checked = true;
                                }
                            }
                        });
                        if (field.type === 'checkbox_with_other') {
                            const otherCheckboxEl = containerElement.querySelector(`#${field.id}_other_checkbox_view`);
                            const otherTxtEl = containerElement.querySelector(`#${field.id}_other_text_view`);
                            if (otherTxtEl) {
                                otherTxtEl.value = otherTextValue || '';
                                if ((otherCheckboxEl && otherCheckboxEl.checked) || (otherTextValue && otherTextValue.trim() !== "")) {
                                    otherTxtEl.classList.remove('hidden');
                                } else {
                                    otherTxtEl.classList.add('hidden');
                                }
                            }
                        }
                    } else {
                        const element = containerElement.querySelector(`#${field.id + viewFieldIdSuffix}`); 
                        if (element && valueFromSheet !== undefined) {
                            element.value = valueFromSheet;
                        }
                        if (field.type === 'checkbox_with_other') {
                            const otherTxtEl = containerElement.querySelector(`#${field.id}_other_text_view`);
                            if (otherTxtEl) {
                                otherTxtEl.value = otherTextValue || '';
                                if(otherTextValue && otherTextValue.trim() !== "") otherTxtEl.classList.remove('hidden');
                                else otherTxtEl.classList.add('hidden');
                            }
                        }
                    }
                }
            });
        }
    });
}

async function generatePdfFromView() {
    const reportContent = document.getElementById('formDisplayForAdmin');
    const reportTitleText = document.getElementById('adminFormViewInfo') ? document.getElementById('adminFormViewInfo').textContent : "گزارش بازدید";
    const downloadBtn = document.getElementById('downloadPdfButton');
    if (!reportContent || !downloadBtn) { alert('محتوایی برای تبدیل به PDF یافت نشد یا دکمه دانلود موجود نیست.'); return; }
    
    const { jsPDF } = window.jspdf;
    if (!jsPDF) {
        alert("کتابخانه jsPDF بارگذاری نشده است.");
        return;
    }
    if (typeof html2canvas === 'undefined') {
        alert("کتابخانه html2canvas بارگذاری نشده است.");
        return;
    }

    downloadBtn.textContent = "در حال آماده‌سازی PDF..."; downloadBtn.disabled = true;

    const hiddenSections = [];
    if (formDisplayForAdmin) {
        formDisplayForAdmin.querySelectorAll('.form-section.hidden').forEach(sec => {
            sec.classList.remove('hidden');
            hiddenSections.push(sec);
        });
    }
    const originalNavDisplay = formDisplayForAdminNav ? formDisplayForAdminNav.style.display : "";
    if (formDisplayForAdminNav) formDisplayForAdminNav.style.display = 'none';


    try {
        const canvas = await html2canvas(reportContent, {
            scale: 1.5, 
            useCORS: true,
            logging: false,
            windowWidth: reportContent.scrollWidth,
            windowHeight: reportContent.scrollHeight,
            onclone: (doc) => {
                 const navInClone = doc.getElementById('formDisplayForAdminNav'); 
                 if (navInClone) navInClone.style.display = 'none';
                 doc.querySelectorAll('.form-section.hidden').forEach(s => s.classList.remove('hidden'));
            }
        });
        const imgData = canvas.toDataURL('image/jpeg', 0.85); 
        const pdf = new jsPDF({ orientation: 'p', unit: 'pt', format: 'a4' });
        
        if (typeof vazirmatnFont !== 'undefined' && vazirmatnFont && vazirmatnFont !== 'PLACEHOLDER_FOR_VAZIRMATN_FONT_BASE64_STRING' && !vazirmatnFont.startsWith('AAEAAAASAQAABAAgR0RFRgAAAAgAAAHgR0RFRgAbOBMAAAK0T1MvMgAAAABAAAAAYGNtYXAAAA')) {
            try {
                pdf.addFileToVFS("Vazirmatn-Regular.ttf", vazirmatnFont); 
                pdf.addFont("Vazirmatn-Regular.ttf", "Vazirmatn", "normal");
                pdf.setFont("Vazirmatn");
            } catch (fontError) {
                console.error("Error adding Vazirmatn font to PDF, falling back to default:", fontError);
                pdf.setFont("helvetica", "normal");
            }
        } else {
            console.warn("Vazirmatn font base64 string is not properly defined or is the placeholder. PDF might not render Persian characters correctly.");
            pdf.setFont("helvetica", "normal"); 
        }
        pdf.setR2L(true); 

        const imgProps = pdf.getImageProperties(imgData);
        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        const margin = 40; 
        const contentWidth = pdfWidth - (2 * margin);
        
        const imgTargetWidth = contentWidth;
        const imgTargetHeight = (imgProps.height * imgTargetWidth) / imgProps.width;
        
        let currentY = margin;

        pdf.setFontSize(16);
        const titleLines = pdf.splitTextToSize(reportTitleText, contentWidth - 20); 
        pdf.text(titleLines, pdfWidth / 2, currentY, { align: 'center' });
        currentY += (titleLines.length * 16) + 20;


        let remainingImgHeight = imgTargetHeight;
        let imgCurrentSourceY = 0; 

        while (remainingImgHeight > 0) {
            if (currentY > margin && (pageHeight - currentY - margin < 20) ) { 
                pdf.addPage();
                currentY = margin;
            }
            
            let pageSpaceForImage = pageHeight - currentY - margin;
            if (pageSpaceForImage <= 0 && remainingImgHeight > 0) { 
                 pdf.addPage();
                 currentY = margin;
                 pageSpaceForImage = pageHeight - (2* margin);
            }

            const heightToDrawOnPdf = Math.min(remainingImgHeight, pageSpaceForImage);
            const sourceImageHeightSlice = (heightToDrawOnPdf / imgTargetHeight) * canvas.height;


            const tempCanvas = document.createElement('canvas');
            tempCanvas.width = canvas.width; 
            tempCanvas.height = sourceImageHeightSlice; 
            const tempCtx = tempCanvas.getContext('2d');
            
            tempCtx.drawImage(canvas, 0, imgCurrentSourceY, canvas.width, sourceImageHeightSlice, 0, 0, tempCanvas.width, tempCanvas.height);
            const pageImgData = tempCanvas.toDataURL('image/jpeg', 0.85);

            if (heightToDrawOnPdf > 0) { 
               pdf.addImage(pageImgData, 'JPEG', margin, currentY, imgTargetWidth, heightToDrawOnPdf);
            }
            
            remainingImgHeight -= heightToDrawOnPdf;
            imgCurrentSourceY += sourceImageHeightSlice; 
            currentY += heightToDrawOnPdf + 10; 
        }

        const classCode = sessionStorage.getItem('currentViewingClassCode') || "report";
        const visitPassword = sessionStorage.getItem('currentViewingVisitPassword') || "data";
        pdf.save(`گزارش_بازدید_${classCode}_${visitPassword}.pdf`);
    } catch (error) {
        console.error("Error generating PDF:", error);
        alert("خطا در تولید PDF: " + error.message + (error.stack ? "\n" + error.stack.substring(0,200) : ""));
    } finally {
        downloadBtn.textContent = "دانلود PDF";
        downloadBtn.disabled = false;
        hiddenSections.forEach(sec => sec.classList.add('hidden'));
        if (formDisplayForAdminNav) formDisplayForAdminNav.style.display = originalNavDisplay || 'grid'; 
        
        const activeNavButton = formDisplayForAdminNav ? formDisplayForAdminNav.querySelector('button.active') : null;
        if (activeNavButton) {
            const onclickAttr = activeNavButton.getAttribute('onclick');
            if(onclickAttr) {
                const activeSectionIdMatch = onclickAttr.match(/'([^']+)'/);
                if (activeSectionIdMatch && activeSectionIdMatch[1]) {
                     showFormSectionForAdminView(activeSectionIdMatch[1], 'formDisplayForAdminNav', 'formDisplayForAdmin');
                }
            }
        } else if (formStructure.length > 0 && formDisplayForAdminNav){ 
            showFormSectionForAdminView(formStructure[0].id, 'formDisplayForAdminNav', 'formDisplayForAdmin');
        }
    }
}

// --- Analytics (Partial/Detailed) ---
// فایل: admin.js
// ... (کدهای قبلی تا قبل از loadAnalyticsData) ...

// فایل: admin.js
// ... (کدهای قبلی تا قبل از تابع loadAnalyticsData) ...

async function loadAnalyticsData() { // برای تحلیل آماری جزئی
    if (!analyticsDisplayArea || !analyticsMessageArea || !analyticsClassCodeSelect || !analyticsVisitPasswordInput) {
        console.error("loadAnalyticsData: یکی از المان های ضروری برای تحلیل جزئی یافت نشد.");
        if (analyticsDisplayArea) analyticsDisplayArea.innerHTML = '<p class="no-data-message" style="color:red;">خطای داخلی: المان‌های صفحه به درستی بارگذاری نشده‌اند.</p>';
        return;
    }
    
    // پاک کردن نمودارهای قبلی از آرایه و DOM (فقط نمودارهای مربوط به analyticsDisplayArea)
    currentCharts = currentCharts.filter(chart => {
        if (chart.canvas.parentElement && chart.canvas.parentElement.id === 'analyticsDisplayArea') {
            chart.destroy();
            return false; // حذف از آرایه
        }
        return true; // حفظ نمودارهای دیگر (مثلا نمودارهای روند)
    });

    analyticsDisplayArea.innerHTML = '<p class="no-data-message"><em>در حال بارگذاری داده‌های تحلیلی جزئی... لطفاً صبر کنید.</em></p>';
    analyticsMessageArea.classList.add('hidden');
    analyticsMessageArea.innerHTML = '';


    const selectedClassCode = analyticsClassCodeSelect.value;
    let selectedVisitPassword = analyticsVisitPasswordInput.value.trim();

    if (!selectedClassCode) {
        analyticsDisplayArea.innerHTML = '<p class="no-data-message">لطفاً یک کلاس برای تحلیل جزئی انتخاب کنید.</p>'; 
        return;
    }
    
    const payload = { classCode: selectedClassCode };
    // فقط اگر پسورد بازدید وارد شده و ALL_CLASSES انتخاب نشده باشد، آن را به payload اضافه کن
    if (selectedVisitPassword && selectedClassCode !== 'ALL_CLASSES') { 
        payload.visitPassword = selectedVisitPassword; 
    }
    
    console.log("loadAnalyticsData: Payload for getAggregatedFormData:", JSON.stringify(payload));

    const result = await callAppsScript('getAggregatedFormData', payload);
    console.log("loadAnalyticsData: Result from getAggregatedFormData:", JSON.stringify(result, null, 2));

    if (result.status === 'success' && result.data && result.data.aggregatedData) {
        const dataToRender = result.data.aggregatedData;
        const count = result.data.submissionCount;

        // بررسی اینکه آیا dataToRender واقعاً حاوی داده‌ای برای نمایش است
        // به جز average_attendance_percentage که به صورت متریک نمایش داده می‌شود
        let hasChartableData = false;
        for (const key in dataToRender) {
            if (key !== "average_attendance_percentage") {
                if ((dataToRender[key].labels && dataToRender[key].labels.length > 0 && dataToRender[key].data && dataToRender[key].data.some(d => d > 0)) || 
                    (dataToRender[key].entries && dataToRender[key].entries.length > 0 && dataToRender[key].entries.some(e => e.text && e.text.trim() !== "")) ||
                    (dataToRender[key].rowsData && Object.keys(dataToRender[key].rowsData).length > 0) ||
                    (dataToRender[key].averageValue !== undefined && dataToRender[key].totalResponses > 0)
                   ) {
                    hasChartableData = true;
                    break;
                }
            } else if (dataToRender[key].totalRecords > 0) { // اگر فقط متریک حضور وجود دارد
                 hasChartableData = true; // آن را هم به عنوان داده قابل نمایش در نظر بگیر
            }
        }


        if (!hasChartableData && count > 0) {
             analyticsDisplayArea.innerHTML = `<p class="no-data-message">داده‌های فرم برای کلاس '${selectedClassCode}' ${selectedVisitPassword ? `و بازدید '${selectedVisitPassword}'` : ''} یافت شد (تعداد ${count} بازدید)، اما شامل مقادیر قابل نمایش در نمودار نبود. لطفاً داده‌های ورودی فرم را بررسی کنید.</p>`;
             return;
        } else if (!hasChartableData && count === 0){
            analyticsDisplayArea.innerHTML = `<p class="no-data-message">داده‌ای برای تحلیل با معیارهای انتخابی یافت نشد (تعداد بازدید: ${count}).</p>`; 
            return;
        }
        
        analyticsDisplayArea.innerHTML = ''; // پاک کردن پیام "در حال بارگذاری"
        renderCharts(dataToRender, selectedClassCode, selectedVisitPassword, count); // selectedVisitPassword برای استفاده در عنوان و tooltip است

    } else if (result.data && (result.data.noData || result.data.error) ) {
         analyticsDisplayArea.innerHTML = `<p class="no-data-message">${result.data.message || 'خطا یا داده‌ای یافت نشد.'} (تعداد بازدیدهای بررسی شده: ${result.data.submissionCount || 0})</p>`;
    } else {
        const errorMsg = (result.data && result.data.message) || result.message || 'خطای نامشخص در دریافت داده.';
        analyticsDisplayArea.innerHTML = `<p class="no-data-message" style="color:red;">خطا در بارگذاری داده‌های تحلیلی جزئی: ${errorMsg}</p>`;
        if(analyticsMessageArea) displayMessage(analyticsMessageArea, `خطا در بارگذاری تحلیل جزئی: ${errorMsg}`, false);
        console.error("loadAnalyticsData: Error or unexpected response:", result);
    }
}

function renderCharts(aggregatedData, forClass, forVisit, submissionCount) { 
    if (!analyticsDisplayArea) {
        console.error("renderCharts: Element analyticsDisplayArea not found");
        return;
    }
    analyticsDisplayArea.innerHTML = ''; 

    let chartsActuallyRendered = 0; // شمارنده برای نمودارهای واقعی رندر شده

    const overallTitleEl = document.createElement('h4');
    overallTitleEl.className = 'analytics-overall-title';
    let reportTitleContent = "<strong>تحلیل آماری جزئی ";
    if (forClass === 'ALL_CLASSES') {
        reportTitleContent += `کلی همه کلاس‌ها`;
    } else {
        const selectedClassOptionElement = analyticsClassCodeSelect.options[analyticsClassCodeSelect.selectedIndex];
        const selectedClassOptionText = selectedClassOptionElement ? selectedClassOptionElement.text.split(' (کد:')[0] : forClass;
        reportTitleContent += `کلاس: </strong> ${selectedClassOptionText}`;
    }
    if(forVisit && forClass !== 'ALL_CLASSES') { 
        reportTitleContent += `<strong> | بازدید شماره: </strong> ${forVisit}`;
    } else if (submissionCount > 0) { // فقط اگر forVisit نباشد، تعداد بازدید را نشان بده
         reportTitleContent += ` (بر اساس <strong>${submissionCount}</strong> بازدید ثبت شده)`;
    } else {
        reportTitleContent += ` (داده‌ای یافت نشد)`;
    }
    overallTitleEl.innerHTML = reportTitleContent;
    analyticsDisplayArea.appendChild(overallTitleEl);


    if (aggregatedData["average_attendance_percentage"] && aggregatedData["average_attendance_percentage"].totalRecords > 0) {
        const attendanceData = aggregatedData["average_attendance_percentage"];
        const metricContainer = document.createElement('div');
        metricContainer.className = 'chart-container metric-container';
        const metricTitle = document.createElement('h5');
        metricTitle.textContent = attendanceData.questionLabel;
        metricContainer.appendChild(metricTitle);
        const metricValueP = document.createElement('p');
        metricValueP.className = 'metric-display';
        metricValueP.innerHTML = `میانگین: <span>${attendanceData.value}${attendanceData.unit || ''}</span> (از ${attendanceData.totalRecords || 0} رکورد)`;
        metricContainer.appendChild(metricValueP);
        analyticsDisplayArea.appendChild(metricContainer);
        chartsActuallyRendered++;
    }

    for (const fieldId_or_GroupId in aggregatedData) {
        if (fieldId_or_GroupId === "average_attendance_percentage") continue; 

        const chartGroupData = aggregatedData[fieldId_or_GroupId];
        // از رندر کردن فیلدهایی که فقط داده روند دارند (و برای تحلیل جزئی نیستند) خودداری کن
        if (chartGroupData.trendData && (!chartGroupData.labels || chartGroupData.labels.length === 0) && (!chartGroupData.entries || chartGroupData.entries.length === 0) && (!chartGroupData.rowsData) && chartGroupData.averageValue === undefined) {
            continue;
        }

        const chartContainer = document.createElement('div');
        chartContainer.className = 'chart-container';
        const title = document.createElement('h5');
        title.textContent = chartGroupData.questionLabel || fieldId_or_GroupId;
        chartContainer.appendChild(title);

        if ((chartGroupData.type === 'select_score' || chartGroupData.type === 'number') && chartGroupData.averageValue !== undefined && chartGroupData.totalResponses > 0) {
            const avgP = document.createElement('p'); avgP.className = 'average-score-display';
            const avgLabel = chartGroupData.type === 'select_score' ? 'میانگین امتیاز کلی' : 'میانگین مقدار';
            avgP.innerHTML = `${avgLabel}: <span>${chartGroupData.averageValue}</span> (از ${chartGroupData.totalResponses || 0} پاسخ)`;
            chartContainer.appendChild(avgP);
        }

        let hasDisplayableContentForThisChart = false; 
        if(chartGroupData.type === 'percentage_rows' && chartGroupData.rowsData && Object.keys(chartGroupData.rowsData).length > 0) {
            let contentHtml = '<ul style="padding-right: 10px; margin-top: 5px; list-style-position: inside;">'; 
            let groupHasData = false;
            for(const subRowId in chartGroupData.rowsData){ 
                const subRowItem = chartGroupData.rowsData[subRowId]; 
                let sum = 0; let totalWeight = 0; 
                if (Array.isArray(subRowItem.data) && Array.isArray(subRowItem.labels)) { 
                    subRowItem.data.forEach((count, index) => { 
                        if (subRowItem.labels[index] !== undefined) { 
                            const percentValue = parseInt(subRowItem.labels[index]); 
                            if (!isNaN(percentValue) && !isNaN(count) && count > 0) { 
                                sum += percentValue * count; 
                                totalWeight += count; 
                            } 
                        } 
                    }); 
                } 
                if (totalWeight > 0) { // Only add if there are responses for this sub-row
                    const averagePercent = (sum / totalWeight).toFixed(1); 
                    contentHtml += `<li style="margin-bottom: 4px;">${subRowItem.label || subRowId}: میانگین <strong>${averagePercent}%</strong> (از ${totalWeight} پاسخ)</li>`; 
                    groupHasData = true;
                }
            } 
            contentHtml += '</ul>'; 
            if (groupHasData) {
                const listContainer = document.createElement('div'); 
                listContainer.innerHTML = contentHtml; 
                chartContainer.appendChild(listContainer);
                hasDisplayableContentForThisChart = true;
            }
        } else if (chartGroupData.type === 'text_multi_visit' && chartGroupData.entries && chartGroupData.entries.some(e=> e.text && e.text.trim() !== "")) {
            const listElement = document.createElement('div'); listElement.className = 'text-entry-container';
            let actualEntriesDisplayed = 0;
            chartGroupData.entries.forEach(entry => {
                if (entry.text && entry.text.trim() !== "") { 
                    const entryP = document.createElement('p'); entryP.className = 'text-entry';
                    let entryLabel = "";
                    // برای تحلیل جزئی یک بازدید خاص، نیازی به نمایش شماره بازدید نیست مگر اینکه forVisit تعریف نشده باشد (یعنی تجمیع یک کلاس)
                    if (!forVisit && submissionCount > 1 && entry.visitPassword) { 
                         entryLabel = `<strong>بازدید ${entry.visitPassword} (${entry.submissionDate || ''}):</strong> `;
                    } else if (forClass === 'ALL_CLASSES' && entry.classCode && entry.visitPassword) {
                        const classInfoOpt = analyticsClassCodeSelect ? analyticsClassCodeSelect.querySelector(`option[value="${entry.classCode}"]`) : null;
                        const classNameForLabel = classInfoOpt ? classInfoOpt.textContent.split(' (کد:')[0] : entry.classCode;
                        entryLabel = `<strong>کلاس ${classNameForLabel} - بازدید ${entry.visitPassword} (${entry.submissionDate || ''}):</strong> `;
                    }

                    entryP.innerHTML = `${entryLabel}${entry.text.replace(/\n/g, '<br>')}`;
                    listElement.appendChild(entryP);
                    actualEntriesDisplayed++;
                }
            });
            if(actualEntriesDisplayed > 0) { 
                chartContainer.appendChild(listElement); 
                hasDisplayableContentForThisChart = true;
            }
        } else if (chartGroupData.labels && chartGroupData.data && chartGroupData.data.some(d => d > 0)) { // Only render chart if there's actual data
            const canvas = document.createElement('canvas'); chartContainer.appendChild(canvas);
            let chartType = 'bar';
            Chart.defaults.font.family = 'Vazirmatn';
            let chartOptions = { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { 
                    legend: { display: true, position: 'top', labels: {font: { family: 'Vazirmatn' }}}, 
                    title: { display: false }, 
                    tooltip: {
                        titleFont: {family: 'Vazirmatn'}, 
                        bodyFont: {family: 'Vazirmatn'}, 
                        callbacks: { 
                            label: function(context) { 
                                let value = context.raw; 
                                let itemLabel = context.label || ''; 
                                if (context.chart.config.type === 'doughnut' || context.chart.config.type === 'pie') {
                                    const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) + '%' : '0%';
                                    return `${itemLabel}: ${value} رأی (${percentage})`;
                                } else if (chartGroupData.id === 'gr_game_duration_minutes' && context.parsed.y !== null) {
                                    return `${itemLabel}: ${context.parsed.y} دقیقه`;
                                } else if (chartGroupData.type === 'select_score' && context.parsed.y !== null) {
                                    return `امتیاز ${itemLabel}: ${context.parsed.y} رأی`;
                                } else if (context.parsed.y !== null) { 
                                    return `${itemLabel}: ${context.parsed.y} رأی`;
                                } else if (context.parsed.x !== null) { 
                                    return `${itemLabel}: ${context.parsed.x} رأی`;
                                }
                                return `${itemLabel}: ${value}`; 
                            } 
                        } 
                    } 
                }, 
                scales: { 
                    y: { beginAtZero: true, ticks: { precision: 0, font: {family: 'Vazirmatn'}, callback: function(value) { if (Number.isInteger(value)) return value;} } }, 
                    x: {ticks: {font: {family: 'Vazirmatn'}}} 
                } 
            };

            if (chartGroupData.type === 'select_score' || chartGroupData.id === 'gr_game_duration_minutes') {
                chartType = 'bar'; 
                chartOptions.plugins.legend.display = false;
                chartOptions.scales.x = { ticks: { autoSkip: false, maxRotation: 0, minRotation: 0, font: {family: 'Vazirmatn'}} };
                if (chartGroupData.id === 'gr_game_duration_minutes') { 
                    chartOptions.scales.y.title = {display: true, text: 'دقیقه', font: {family: 'Vazirmatn'}}; 
                }
            }
            else if (chartGroupData.type === 'radio' || chartGroupData.type === 'select' || ( (chartGroupData.type === 'checkbox' || chartGroupData.type === 'checkbox_with_other') && chartGroupData.labels.length > 0 && chartGroupData.labels.length < 6 ) ) { 
                chartType = 'doughnut'; 
                chartOptions.scales = {}; 
                chartOptions.plugins.legend.position = 'right'; 
            } 
            else if (chartGroupData.type === 'checkbox' || chartGroupData.type === 'checkbox_with_other') { 
                chartType = 'bar'; 
                chartOptions.indexAxis = 'y'; 
                chartOptions.scales = { 
                    x: { beginAtZero: true, ticks: { precision: 0, font: {family: 'Vazirmatn'}, callback: function(value) { if (Number.isInteger(value)) return value;} } }, 
                    y: {ticks: {font: {family: 'Vazirmatn'}}} 
                }; 
                chartOptions.plugins.legend.display = false; 
            }
            
            // Only create chart if there are labels and some data
            if (chartGroupData.labels && chartGroupData.labels.length > 0 && chartGroupData.data && chartGroupData.data.some(d => d > 0)) {
                const newChart = new Chart(canvas, { 
                    type: chartType, 
                    data: { 
                        labels: chartGroupData.labels, 
                        datasets: [{ 
                            label: chartGroupData.questionLabel || 'تعداد پاسخ', 
                            data: chartGroupData.data, 
                            backgroundColor: generateDistinctColors(chartGroupData.labels.length, false, (chartType === 'pie' || chartType === 'doughnut')), 
                            borderColor: generateDistinctColors(chartGroupData.labels.length, true, (chartType === 'pie' || chartType === 'doughnut')), 
                            borderWidth: 1 
                        }] 
                    }, 
                    options: chartOptions 
                }); 
                currentCharts.push(newChart); 
                hasDisplayableContentForThisChart = true;
            } else {
                canvas.remove(); // Remove canvas if no actual data to plot
            }
        }

        if (hasDisplayableContentForThisChart) {
            analyticsDisplayArea.appendChild(chartContainer);
            chartsActuallyRendered++;
        } else if (chartGroupData.type !== 'metric' && Object.keys(chartGroupData).length > 1) { // If it was a chartable type but had no data
            const noDataMsg = document.createElement('p');
            noDataMsg.className = 'no-data-message';
            noDataMsg.textContent = `داده‌ای برای سوال "${chartGroupData.questionLabel || fieldId_or_GroupId}" یافت نشد.`;
            chartContainer.appendChild(noDataMsg);
            analyticsDisplayArea.appendChild(chartContainer);
            chartsActuallyRendered++; // Count as rendered to avoid "no charts" message
        }
    }

    if (chartsActuallyRendered === 0) {
        // If overall title was added, but nothing else, display a more specific message
        if (analyticsDisplayArea.querySelector('.analytics-overall-title')) {
             analyticsDisplayArea.innerHTML += '<p class="no-data-message">هیچ نمودار یا داده قابل نمایشی برای معیارهای انتخابی یافت نشد.</p>';
        } else { // Should not happen if overall title is always added
             analyticsDisplayArea.innerHTML = '<p class="no-data-message">هیچ داده‌ای برای نمایش وجود ندارد.</p>';
        }
    }
}


function renderCharts(aggregatedData, forClass, forVisit, submissionCount) { 
    if (!analyticsDisplayArea) {
        console.error("Element analyticsDisplayArea not found in renderCharts");
        return;
    }
    // این تابع فقط نمودارهای جزئی را در analyticsDisplayArea رندر می‌کند

    analyticsDisplayArea.innerHTML = ''; // پاک کردن محتوای قبلی این بخش

    let mainChartsRendered = 0;
    const overallTitleEl = document.createElement('h4');
    overallTitleEl.className = 'analytics-overall-title';
    let reportTitleContent = "<strong>تحلیل آماری جزئی ";
    if (forClass === 'ALL_CLASSES') {
        reportTitleContent += `کلی همه کلاس‌ها`;
    } else {
        const selectedClassOptionText = analyticsClassCodeSelect.options[analyticsClassCodeSelect.selectedIndex]?.text.split(' (کد:')[0] || forClass;
        reportTitleContent += `کلاس: </strong> ${selectedClassOptionText}`;
    }
    if(forVisit && forClass !== 'ALL_CLASSES') { 
        reportTitleContent += `<strong> | بازدید شماره: </strong> ${forVisit}`;
    } else {
         reportTitleContent += ` (بر اساس <strong>${submissionCount || 0}</strong> بازدید ثبت شده)`;
    }
    overallTitleEl.innerHTML = reportTitleContent;
    analyticsDisplayArea.appendChild(overallTitleEl);


    if (aggregatedData["average_attendance_percentage"]) {
        const attendanceData = aggregatedData["average_attendance_percentage"];
        const metricContainer = document.createElement('div');
        metricContainer.className = 'chart-container metric-container';
        const metricTitle = document.createElement('h5');
        metricTitle.textContent = attendanceData.questionLabel;
        metricContainer.appendChild(metricTitle);
        const metricValueP = document.createElement('p');
        metricValueP.className = 'metric-display';
        metricValueP.innerHTML = `میانگین: <span>${attendanceData.value}${attendanceData.unit || ''}</span> (از ${attendanceData.totalRecords || 0} رکورد)`;
        metricContainer.appendChild(metricValueP);
        analyticsDisplayArea.appendChild(metricContainer);
        mainChartsRendered++;
    }

    for (const fieldId_or_GroupId in aggregatedData) {
        if (fieldId_or_GroupId === "average_attendance_percentage" || aggregatedData[fieldId_or_GroupId].trendData) {
            // از نمایش داده‌های روند در این بخش خودداری می‌کنیم، مگر اینکه داده‌های عادی هم داشته باشند
             if (aggregatedData[fieldId_or_GroupId].trendData && (!aggregatedData[fieldId_or_GroupId].labels || aggregatedData[fieldId_or_GroupId].labels.length === 0) && (!aggregatedData[fieldId_or_GroupId].entries || aggregatedData[fieldId_or_GroupId].entries.length === 0) && (!aggregatedData[fieldId_or_GroupId].rowsData)) {
                continue; // اگر فقط داده روند دارد و داده عادی برای چارت ندارد، اینجا رندر نکن
             }
        }

        const chartGroupData = aggregatedData[fieldId_or_GroupId];
        const chartContainer = document.createElement('div');
        chartContainer.className = 'chart-container';
        const title = document.createElement('h5');
        title.textContent = chartGroupData.questionLabel || fieldId_or_GroupId;
        chartContainer.appendChild(title);

        if ((chartGroupData.type === 'select_score' || chartGroupData.type === 'number') && chartGroupData.averageValue !== undefined) {
            const avgP = document.createElement('p'); avgP.className = 'average-score-display';
            const avgLabel = chartGroupData.type === 'select_score' ? 'میانگین امتیاز کلی' : 'میانگین مقدار';
            avgP.innerHTML = `${avgLabel}: <span>${chartGroupData.averageValue}</span> (از ${chartGroupData.totalResponses || 0} پاسخ)`;
            chartContainer.appendChild(avgP);
        }
        let hasDisplayableContentForThisChart = false; 
        if(chartGroupData.type === 'percentage_rows' && chartGroupData.rowsData && Object.keys(chartGroupData.rowsData).length > 0) {
            let contentHtml = '<ul style="padding-right: 10px; margin-top: 5px; list-style-position: inside;">'; 
            let totalResponsesForGroup = 0;
            for(const subRowId in chartGroupData.rowsData){ 
                const subRowItem = chartGroupData.rowsData[subRowId]; 
                let sum = 0; let totalWeight = 0; 
                let validResponseCountForRow = 0; 
                if (Array.isArray(subRowItem.data) && Array.isArray(subRowItem.labels)) { 
                    subRowItem.data.forEach((count, index) => { 
                        if (subRowItem.labels[index] !== undefined) { 
                            const percentValue = parseInt(subRowItem.labels[index]); 
                            if (!isNaN(percentValue) && !isNaN(count) && count > 0) { 
                                sum += percentValue * count; 
                                totalWeight += count; 
                                validResponseCountForRow += count;
                            } 
                        } 
                    }); 
                } 
                if (validResponseCountForRow > 0) totalResponsesForGroup += validResponseCountForRow; // This might not be accurate if user can select multiple rows
                const averagePercent = totalWeight > 0 ? (sum / totalWeight).toFixed(1) : "N/A"; 
                contentHtml += `<li style="margin-bottom: 4px;">${subRowItem.label || subRowId}: میانگین <strong>${averagePercent}%</strong> (از ${totalWeight} پاسخ)</li>`; 
            } 
            contentHtml += '</ul>'; 
            const listContainer = document.createElement('div'); 
            listContainer.innerHTML = contentHtml; 
            chartContainer.appendChild(listContainer);
            hasDisplayableContentForThisChart = true;
            mainChartsRendered++;
        } else if (chartGroupData.type === 'text_multi_visit' && chartGroupData.entries) {
            if (!chartGroupData.entries || chartGroupData.entries.length === 0 || (chartGroupData.entries.length === 1 && chartGroupData.entries[0].text.trim() === "" && (forVisit && submissionCount === 1) )) { // Adjusted condition for single visit empty text
                const noEntriesMsg = document.createElement('p'); noEntriesMsg.className = 'no-data-message';
                noEntriesMsg.textContent = (forVisit && submissionCount === 1 && chartGroupData.entries.length === 1 && chartGroupData.entries[0].text.trim() === "") ? '(اطلاعاتی برای این بازدید ثبت نشده)' : 'پاسخ متنی برای نمایش وجود ندارد.';
                chartContainer.appendChild(noEntriesMsg);
            } else {
                const listElement = document.createElement('div'); listElement.className = 'text-entry-container';
                let actualEntriesDisplayed = 0;
                chartGroupData.entries.forEach(entry => {
                    if ((entry.text && entry.text.trim() !== "") || ((forVisit && submissionCount === 1) && entry.text.trim() === "")) { 
                        const entryP = document.createElement('p'); entryP.className = 'text-entry';
                        let entryLabel = "";
                        if ((!forVisit || forClass === 'ALL_CLASSES') && submissionCount > 1 && chartGroupData.entries.length > 1 && !forVisit) {  
                            if (forClass === 'ALL_CLASSES' && entry.classCode) { 
                                const classInfoOpt = analyticsClassCodeSelect ? analyticsClassCodeSelect.querySelector(`option[value="${entry.classCode}"]`) : null;
                                const classNameForLabel = classInfoOpt ? classInfoOpt.textContent.split(' (کد:')[0] : entry.classCode;
                                entryLabel = `<strong>کلاس ${classNameForLabel} - بازدید ${entry.visitPassword} (${entry.submissionDate || ''}):</strong> `;
                            } else if (entry.visitPassword) {
                                entryLabel = `<strong>بازدید ${entry.visitPassword} (${entry.submissionDate || ''}):</strong> `;
                            }
                        } else if (forVisit && entry.visitPassword) { // For single visit, show visit password if available
                             entryLabel = `<strong>بازدید ${entry.visitPassword} (${entry.submissionDate || ''}):</strong> `;
                        }
                        entryP.innerHTML = `${entryLabel}${entry.text ? entry.text.replace(/\n/g, '<br>') : '(بدون پاسخ)'}`;
                        listElement.appendChild(entryP);
                        actualEntriesDisplayed++;
                    }
                });
                if(actualEntriesDisplayed > 0) { 
                    chartContainer.appendChild(listElement); 
                    hasDisplayableContentForThisChart = true;
                    mainChartsRendered++;
                } else { 
                    const noEntriesMsg = document.createElement('p'); noEntriesMsg.className = 'no-data-message'; 
                    noEntriesMsg.textContent = 'پاسخ متنی برای نمایش وجود ندارد.'; 
                    chartContainer.appendChild(noEntriesMsg); 
                }
            }
        } else if (chartGroupData.labels && chartGroupData.data) {
            if (chartGroupData.labels.length === 0 || chartGroupData.data.every(d => d === 0) ) {
                 if (chartGroupData.data.every(d => d === 0) && chartGroupData.labels.length > 0) {
                     const allZeroMsg = document.createElement('p'); allZeroMsg.className = 'no-data-message';
                     allZeroMsg.textContent = 'تمامی پاسخ‌های تجمیعی برای این سوال صفر بوده‌اند.';
                     chartContainer.appendChild(allZeroMsg); hasDisplayableContentForThisChart = true; mainChartsRendered++;
                 } else if (chartGroupData.labels.length === 0 && chartGroupData.data.length === 0) {
                    // No labels and no data, effectively no data for chart
                 }
            } else {
                const canvas = document.createElement('canvas'); chartContainer.appendChild(canvas);
                let chartType = 'bar';
                Chart.defaults.font.family = 'Vazirmatn';
                let chartOptions = { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    plugins: { 
                        legend: { display: true, position: 'top', labels: {font: { family: 'Vazirmatn' }}}, 
                        title: { display: false }, 
                        tooltip: {
                            titleFont: {family: 'Vazirmatn'}, 
                            bodyFont: {family: 'Vazirmatn'}, 
                            callbacks: { 
                                label: function(context) { 
                                    let value = context.raw; 
                                    let itemLabel = context.label || ''; 
                                    
                                    if (context.chart.config.type === 'doughnut' || context.chart.config.type === 'pie') {
                                        const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) + '%' : '0%';
                                        return `${itemLabel}: ${value} رأی (${percentage})`;
                                    } else if (chartGroupData.id === 'gr_game_duration_minutes' && context.parsed.y !== null) {
                                        return `${itemLabel}: ${context.parsed.y} دقیقه`;
                                    } else if (chartGroupData.type === 'select_score' && context.parsed.y !== null) {
                                        return `امتیاز ${itemLabel}: ${context.parsed.y} رأی`;
                                    } else if (context.parsed.y !== null) { 
                                        return `${itemLabel}: ${context.parsed.y} رأی`;
                                    } else if (context.parsed.x !== null) { 
                                        return `${itemLabel}: ${context.parsed.x} رأی`;
                                    }
                                    return `${itemLabel}: ${value}`; 
                                } 
                            } 
                        } 
                    }, 
                    scales: { 
                        y: { beginAtZero: true, ticks: { precision: 0, font: {family: 'Vazirmatn'}, callback: function(value) { if (Number.isInteger(value)) return value;} } }, 
                        x: {ticks: {font: {family: 'Vazirmatn'}}} 
                    } 
                };

                if (chartGroupData.type === 'select_score' || chartGroupData.id === 'gr_game_duration_minutes') {
                    chartType = 'bar'; 
                    chartOptions.plugins.legend.display = false;
                    chartOptions.scales.x = { ticks: { autoSkip: false, maxRotation: 0, minRotation: 0, font: {family: 'Vazirmatn'}} };
                    if (chartGroupData.id === 'gr_game_duration_minutes') { 
                        chartOptions.scales.y.title = {display: true, text: 'دقیقه', font: {family: 'Vazirmatn'}}; 
                    }
                }
                else if (chartGroupData.type === 'radio' || chartGroupData.type === 'select' || ( (chartGroupData.type === 'checkbox' || chartGroupData.type === 'checkbox_with_other') && chartGroupData.labels.length > 0 && chartGroupData.labels.length < 6 ) ) { // Doughnut for few items
                    chartType = 'doughnut'; 
                    chartOptions.scales = {}; 
                    chartOptions.plugins.legend.position = 'right'; 
                } 
                else if (chartGroupData.type === 'checkbox' || chartGroupData.type === 'checkbox_with_other') { 
                    chartType = 'bar'; 
                    chartOptions.indexAxis = 'y'; 
                    chartOptions.scales = { 
                        x: { beginAtZero: true, ticks: { precision: 0, font: {family: 'Vazirmatn'}, callback: function(value) { if (Number.isInteger(value)) return value;} } }, 
                        y: {ticks: {font: {family: 'Vazirmatn'}}} 
                    }; 
                    chartOptions.plugins.legend.display = false; 
                }

                if(!((chartType === 'pie' || chartType === 'doughnut') && chartGroupData.data.every(d => d === 0) && chartGroupData.labels.length > 0) && chartGroupData.labels.length > 0) { 
                    const newChart = new Chart(canvas, { 
                        type: chartType, 
                        data: { 
                            labels: chartGroupData.labels, 
                            datasets: [{ 
                                label: chartGroupData.questionLabel || 'تعداد پاسخ', 
                                data: chartGroupData.data, 
                                backgroundColor: generateDistinctColors(chartGroupData.labels.length, false, (chartType === 'pie' || chartType === 'doughnut')), 
                                borderColor: generateDistinctColors(chartGroupData.labels.length, true, (chartType === 'pie' || chartType === 'doughnut')), 
                                borderWidth: 1 
                            }] 
                        }, 
                        options: chartOptions 
                    }); 
                    currentCharts.push(newChart); 
                    hasDisplayableContentForThisChart = true; mainChartsRendered++;
                } else { 
                    canvas.remove(); // Remove canvas if no data or all zero for pie/doughnut
                }
            }
        }

        if (!hasDisplayableContentForThisChart && chartGroupData.type !== 'metric') {
             if (!chartContainer.querySelector('.no-data-message')) {
                const noDataMsg = document.createElement('p'); noDataMsg.className = 'no-data-message';
                noDataMsg.textContent = 'داده‌ای برای نمایش این سوال یافت نشد.';
                chartContainer.appendChild(noDataMsg);
            }
        }
        // Only append the container if it has some content (chart, list, or no-data message specific to it)
        if (chartContainer.innerHTML.includes('<canvas') || chartContainer.innerHTML.includes('<ul>') || chartContainer.innerHTML.includes('text-entry-container') || chartContainer.querySelector('.no-data-message')) {
            analyticsDisplayArea.appendChild(chartContainer);
        }
    }
     if (mainChartsRendered === 0 && !aggregatedData["average_attendance_percentage"]) { 
        if (analyticsDisplayArea.querySelectorAll('.chart-container').length === 0 && analyticsDisplayArea.querySelectorAll('.metric-container').length === 0) {
            // If after looping, nothing was added beyond the overall title
            analyticsDisplayArea.innerHTML += '<p class="no-data-message">هیچ نمودار یا داده قابل نمایشی برای معیارهای انتخابی وجود ندارد.</p>';
        }
    } else if (analyticsDisplayArea.children.length <=1 && !aggregatedData["average_attendance_percentage"]) { // only overall title exists
         analyticsDisplayArea.innerHTML += '<p class="no-data-message">هیچ نمودار یا داده قابل نمایشی برای معیارهای انتخابی وجود ندارد.</p>';
    }
}

// ... (بقیه کد admin.js شامل renderTrendChartsInDedicatedArea, generateDistinctColors, vazirmatnFont و DOMContentLoaded بدون تغییر باقی می‌ماند) ...

function renderCharts(aggregatedData, forClass, forVisit, submissionCount) { 
    if (!analyticsDisplayArea || !analyticsClassCodeSelect) return;
    // This function is now only for detailed charts in #analyticsDisplayArea
    // It will NOT render trend charts from chartGroupData.trendData here.
    // Those are handled by renderTrendChartsInDedicatedArea.

    analyticsDisplayArea.innerHTML = ''; // Clear previous content from this specific area

    let mainChartsRendered = 0;
    const overallTitleEl = document.createElement('h4');
    overallTitleEl.className = 'analytics-overall-title';
    let reportTitleContent = "<strong>تحلیل ";
    if (forClass === 'ALL_CLASSES') {
        reportTitleContent += `کلی همه کلاس‌ها`;
    } else {
        const selectedClassOption = Array.from(analyticsClassCodeSelect.options).find(opt => opt.value === forClass);
        reportTitleContent += `کلاس: </strong> ${selectedClassOption ? selectedClassOption.text.split(' (کد:')[0] : forClass}`;
    }
    if(forVisit && forClass !== 'ALL_CLASSES') { // Only show visit password if a specific class AND specific visit is analyzed
        reportTitleContent += `<strong> | بازدید شماره: </strong> ${forVisit}`;
    } else {
         reportTitleContent += ` (بر اساس <strong>${submissionCount || 0}</strong> بازدید ثبت شده)`;
    }
    overallTitleEl.innerHTML = reportTitleContent;
    analyticsDisplayArea.appendChild(overallTitleEl);


    if (aggregatedData["average_attendance_percentage"]) {
        const attendanceData = aggregatedData["average_attendance_percentage"];
        const metricContainer = document.createElement('div');
        metricContainer.className = 'chart-container metric-container';
        const metricTitle = document.createElement('h5');
        metricTitle.textContent = attendanceData.questionLabel;
        metricContainer.appendChild(metricTitle);
        const metricValueP = document.createElement('p');
        metricValueP.className = 'metric-display';
        metricValueP.innerHTML = `میانگین: <span>${attendanceData.value}${attendanceData.unit || ''}</span> (از ${attendanceData.totalRecords || 0} رکورد)`;
        metricContainer.appendChild(metricValueP);
        analyticsDisplayArea.appendChild(metricContainer);
        mainChartsRendered++;
    }

    for (const fieldId_or_GroupId in aggregatedData) {
        if (fieldId_or_GroupId === "average_attendance_percentage" || fieldId_or_GroupId === "trendDataGlobalKey") continue; // Skip metric and global trend key

        const chartGroupData = aggregatedData[fieldId_or_GroupId];
        // Do not render if it's just trend data for a specific field (that's for the other section)
        if (chartGroupData.trendData && (!chartGroupData.labels || chartGroupData.labels.length === 0) && (!chartGroupData.entries || chartGroupData.entries.length === 0) && (!chartGroupData.rowsData)) {
             // If it only contains trend data and no other chartable main data, skip rendering it here.
            continue;
        }


        const chartContainer = document.createElement('div');
        chartContainer.className = 'chart-container';
        const title = document.createElement('h5');
        title.textContent = chartGroupData.questionLabel || fieldId_or_GroupId;
        chartContainer.appendChild(title);

        if ((chartGroupData.type === 'select_score' || chartGroupData.type === 'number') && chartGroupData.averageValue !== undefined) {
            const avgP = document.createElement('p'); avgP.className = 'average-score-display';
            const avgLabel = chartGroupData.type === 'select_score' ? 'میانگین امتیاز کلی' : 'میانگین مقدار';
            avgP.innerHTML = `${avgLabel}: <span>${chartGroupData.averageValue}</span> (از ${chartGroupData.totalResponses || 0} پاسخ)`;
            chartContainer.appendChild(avgP);
        }
        let hasDisplayableContentForThisChart = false; 
        if(chartGroupData.type === 'percentage_rows' && chartGroupData.rowsData && Object.keys(chartGroupData.rowsData).length > 0) {
            let contentHtml = '<ul style="padding-right: 10px; margin-top: 5px; list-style-position: inside;">'; 
            for(const subRowId in chartGroupData.rowsData){ 
                const subRowItem = chartGroupData.rowsData[subRowId]; 
                let sum = 0; let totalWeight = 0; 
                let validResponseCountForRow = 0; // Count responses for this specific row
                if (Array.isArray(subRowItem.data) && Array.isArray(subRowItem.labels)) { 
                    subRowItem.data.forEach((count, index) => { 
                        if (subRowItem.labels[index] !== undefined) { 
                            const percentValue = parseInt(subRowItem.labels[index]); 
                            if (!isNaN(percentValue) && !isNaN(count) && count > 0) { // Ensure count > 0
                                sum += percentValue * count; 
                                totalWeight += count; 
                                validResponseCountForRow += count;
                            } 
                        } 
                    }); 
                } 
                const averagePercent = totalWeight > 0 ? (sum / totalWeight).toFixed(1) : "N/A"; 
                contentHtml += `<li style="margin-bottom: 4px;">${subRowItem.label || subRowId}: میانگین <strong>${averagePercent}%</strong> (از ${validResponseCountForRow} پاسخ)</li>`; 
            } 
            contentHtml += '</ul>'; 
            const listContainer = document.createElement('div'); 
            listContainer.innerHTML = contentHtml; 
            chartContainer.appendChild(listContainer);
            hasDisplayableContentForThisChart = true;
            mainChartsRendered++;
        } else if (chartGroupData.type === 'text_multi_visit' && chartGroupData.entries) {
             if (!chartGroupData.entries || chartGroupData.entries.length === 0 || (chartGroupData.entries.length === 1 && chartGroupData.entries[0].text.trim() === "" && (forVisit || submissionCount === 1))) {
                const noEntriesMsg = document.createElement('p'); noEntriesMsg.className = 'no-data-message';
                noEntriesMsg.textContent = (forVisit || submissionCount === 1) && chartGroupData.entries.length === 1 && chartGroupData.entries[0].text.trim() === "" ? '(اطلاعاتی برای این بازدید ثبت نشده)' : 'پاسخ متنی برای نمایش وجود ندارد.';
                chartContainer.appendChild(noEntriesMsg);
            } else {
                const listElement = document.createElement('div'); listElement.className = 'text-entry-container';
                let actualEntriesDisplayed = 0;
                chartGroupData.entries.forEach(entry => {
                    if ((entry.text && entry.text.trim() !== "") || ((forVisit || submissionCount === 1) && entry.text.trim() === "")) { 
                        const entryP = document.createElement('p'); entryP.className = 'text-entry';
                        let entryLabel = "";
                        if ((!forVisit || forClass === 'ALL_CLASSES') && submissionCount > 1 && chartGroupData.entries.length > 1) {  
                            if (forClass === 'ALL_CLASSES' && entry.classCode) { 
                                const classInfoOpt = analyticsClassCodeSelect ? analyticsClassCodeSelect.querySelector(`option[value="${entry.classCode}"]`) : null;
                                const classNameForLabel = classInfoOpt ? classInfoOpt.textContent.split(' (کد:')[0] : entry.classCode;
                                entryLabel = `<strong>کلاس ${classNameForLabel} - بازدید ${entry.visitPassword} (${entry.submissionDate || ''}):</strong> `;
                            } else if (entry.visitPassword) {
                                entryLabel = `<strong>بازدید ${entry.visitPassword} (${entry.submissionDate || ''}):</strong> `;
                            }
                        }
                        entryP.innerHTML = `${entryLabel}${entry.text ? entry.text.replace(/\n/g, '<br>') : '(بدون پاسخ)'}`;
                        listElement.appendChild(entryP);
                        actualEntriesDisplayed++;
                    }
                });
                if(actualEntriesDisplayed > 0) { 
                    chartContainer.appendChild(listElement); 
                    hasDisplayableContentForThisChart = true;
                    mainChartsRendered++;
                } else { 
                    const noEntriesMsg = document.createElement('p'); noEntriesMsg.className = 'no-data-message'; 
                    noEntriesMsg.textContent = 'پاسخ متنی برای نمایش وجود ندارد.'; 
                    chartContainer.appendChild(noEntriesMsg); 
                }
            }
        } else if (chartGroupData.labels && chartGroupData.data) {
            if (chartGroupData.labels.length === 0 || (chartGroupData.data.every(d => d === 0) && chartGroupData.id !== 'gr_game_duration_minutes') ) {
                if (chartGroupData.data.every(d => d === 0) && chartGroupData.labels.length > 0) {
                     const allZeroMsg = document.createElement('p'); allZeroMsg.className = 'no-data-message';
                     allZeroMsg.textContent = 'تمامی پاسخ‌های تجمیعی برای این سوال صفر بوده‌اند.';
                     chartContainer.appendChild(allZeroMsg); hasDisplayableContentForThisChart = true; mainChartsRendered++;
                }
            } else {
                const canvas = document.createElement('canvas'); chartContainer.appendChild(canvas);
                let chartType = 'bar';
                Chart.defaults.font.family = 'Vazirmatn';
                let chartOptions = { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    plugins: { 
                        legend: { display: true, position: 'top', labels: {font: { family: 'Vazirmatn' }}}, 
                        title: { display: false }, 
                        tooltip: {
                            titleFont: {family: 'Vazirmatn'}, 
                            bodyFont: {family: 'Vazirmatn'}, 
                            callbacks: { 
                                label: function(context) { 
                                    let value = context.raw; 
                                    let itemLabel = context.label || ''; 
                                    
                                    if (context.chart.config.type === 'doughnut' || context.chart.config.type === 'pie') {
                                        const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) + '%' : '0%';
                                        return `${itemLabel}: ${value} رأی (${percentage})`;
                                    } else if (chartGroupData.id === 'gr_game_duration_minutes' && context.parsed.y !== null) {
                                        return `${itemLabel}: ${context.parsed.y} دقیقه`;
                                    } else if (chartGroupData.type === 'select_score' && context.parsed.y !== null) {
                                        return `امتیاز ${itemLabel}: ${context.parsed.y} رأی`;
                                    } else if (context.parsed.y !== null) { 
                                        return `${itemLabel}: ${context.parsed.y} رأی`;
                                    } else if (context.parsed.x !== null) { 
                                        return `${itemLabel}: ${context.parsed.x} رأی`;
                                    }
                                    return `${itemLabel}: ${value}`; 
                                } 
                            } 
                        } 
                    }, 
                    scales: { 
                        y: { beginAtZero: true, ticks: { precision: 0, font: {family: 'Vazirmatn'}, callback: function(value) { if (Number.isInteger(value)) return value;} } }, 
                        x: {ticks: {font: {family: 'Vazirmatn'}}} 
                    } 
                };

                if (chartGroupData.type === 'select_score' || chartGroupData.id === 'gr_game_duration_minutes') {
                    chartType = 'bar'; 
                    chartOptions.plugins.legend.display = false;
                    chartOptions.scales.x = { ticks: { autoSkip: false, maxRotation: 0, minRotation: 0, font: {family: 'Vazirmatn'}} };
                    if (chartGroupData.id === 'gr_game_duration_minutes') { 
                        chartOptions.scales.y.title = {display: true, text: 'دقیقه', font: {family: 'Vazirmatn'}}; 
                    }
                }
                else if (chartGroupData.type === 'radio' || chartGroupData.type === 'select' || ( (chartGroupData.type === 'checkbox' || chartGroupData.type === 'checkbox_with_other') && chartGroupData.labels.length < 6 && chartGroupData.labels.length > 0) ) { 
                    chartType = 'doughnut'; 
                    chartOptions.scales = {}; 
                    chartOptions.plugins.legend.position = 'right'; 
                } 
                else if (chartGroupData.type === 'checkbox' || chartGroupData.type === 'checkbox_with_other') { 
                    chartType = 'bar'; 
                    chartOptions.indexAxis = 'y'; 
                    chartOptions.scales = { 
                        x: { beginAtZero: true, ticks: { precision: 0, font: {family: 'Vazirmatn'}, callback: function(value) { if (Number.isInteger(value)) return value;} } }, 
                        y: {ticks: {font: {family: 'Vazirmatn'}}} 
                    }; 
                    chartOptions.plugins.legend.display = false; 
                }

                if((chartType === 'pie' || chartType === 'doughnut') && chartGroupData.data.every(d => d === 0) && chartGroupData.labels.length > 0) { 
                    canvas.remove(); 
                } 
                else if (chartGroupData.labels.length > 0) { 
                    const newChart = new Chart(canvas, { 
                        type: chartType, 
                        data: { 
                            labels: chartGroupData.labels, 
                            datasets: [{ 
                                label: chartGroupData.questionLabel || 'تعداد پاسخ', 
                                data: chartGroupData.data, 
                                backgroundColor: generateDistinctColors(chartGroupData.labels.length, false, (chartType === 'pie' || chartType === 'doughnut')), 
                                borderColor: generateDistinctColors(chartGroupData.labels.length, true, (chartType === 'pie' || chartType === 'doughnut')), 
                                borderWidth: 1 
                            }] 
                        }, 
                        options: chartOptions 
                    }); 
                    currentCharts.push(newChart); 
                    hasDisplayableContentForThisChart = true; mainChartsRendered++;
                }
                else { canvas.remove(); }
            }
        }

        if (!hasDisplayableContentForThisChart && chartGroupData.type !== 'metric') {
             if (!chartContainer.querySelector('.no-data-message')) {
                const noDataMsg = document.createElement('p'); noDataMsg.className = 'no-data-message';
                noDataMsg.textContent = 'داده‌ای برای نمایش این سوال یافت نشد.';
                chartContainer.appendChild(noDataMsg);
            }
        }
        if (hasDisplayableContentForThisChart || (chartGroupData.type !== 'metric' && (chartContainer.querySelector('canvas') || chartContainer.querySelector('ul') || chartContainer.querySelector('.text-entry-container') || chartContainer.querySelector('.no-data-message')) ) ) {
             analyticsDisplayArea.appendChild(chartContainer);
        }
    }
     if (mainChartsRendered === 0 && analyticsDisplayArea.innerHTML.trim() === "" && !aggregatedData["average_attendance_percentage"]) { 
        analyticsDisplayArea.innerHTML = '<p class="no-data-message">هیچ نمودار قابل نمایشی برای معیارهای انتخابی وجود ندارد.</p>';
    } else if (mainChartsRendered === 0 && analyticsDisplayArea.innerHTML.includes('analytics-overall-title') && !aggregatedData["average_attendance_percentage"] ) {
        // If only title is there and no charts and no metric
        analyticsDisplayArea.innerHTML += '<p class="no-data-message">هیچ نمودار قابل نمایشی برای معیارهای انتخابی وجود ندارد.</p>';
    }
}


// --- Trend Analysis Section Specific Functions ---
async function loadTrendAnalysisData() {
    if (!trendAnalysisDisplayArea || !trendAnalysisMessageArea || !trendClassCodeSelect) return;

    // Clear previous trend charts from currentCharts array and DOM
    currentCharts = currentCharts.filter(chart => {
        if (chart.canvas.parentElement && chart.canvas.parentElement.closest('#trendAnalysisDisplayArea')) {
            chart.destroy();
            return false; // Remove from currentCharts
        }
        return true; // Keep other charts
    });
    trendAnalysisDisplayArea.innerHTML = '<p class="no-data-message"><em>در حال بارگذاری داده‌های روند...</em></p>';
    trendAnalysisMessageArea.classList.add('hidden');

    const selectedClassCodeForTrend = trendClassCodeSelect.value;

    if (!selectedClassCodeForTrend) {
        trendAnalysisDisplayArea.innerHTML = '<p class="no-data-message">لطفاً یک کلاس (یا گزینه "روند کلی همه کلاس‌ها") را برای مشاهده روند انتخاب کنید.</p>';
        return;
    }

    // For trend analysis, we never send a specific visitPassword,
    // as we want all data for the selected class(es) over time.
    const payload = { classCode: selectedClassCodeForTrend }; // If "ALL_CLASSES_TREND", backend handles it

    const result = await callAppsScript('getAggregatedFormData', payload);

    if (result.status === 'success' && result.data && result.data.aggregatedData) {
        const dataToRender = result.data.aggregatedData;
        trendAnalysisDisplayArea.innerHTML = ''; // Clear "loading" message

        let trendTitleText = `تحلیل روند پیشرفت برای: `;
        if (selectedClassCodeForTrend === "ALL_CLASSES_TREND") {
            trendTitleText += "همه کلاس‌ها (تجمیع ماهانه)";
        } else {
            const selectedOption = trendClassCodeSelect.options[trendClassCodeSelect.selectedIndex];
            trendTitleText += selectedOption.text;
        }
        // You might want a dedicated title element for this section if not using overallTitle from renderTrendChartsInDedicatedArea
        // For now, renderTrendChartsInDedicatedArea will create its own titles per chart.
        
        renderTrendChartsInDedicatedArea(dataToRender, trendAnalysisDisplayArea, selectedClassCodeForTrend === "ALL_CLASSES_TREND");
    } else if (result.data && (result.data.noData || result.data.error)) {
        trendAnalysisDisplayArea.innerHTML = `<p class="no-data-message">${result.data.message || 'خطا یا داده‌ای برای نمایش روند یافت نشد.'} (بازدیدهای بررسی شده: ${result.data.submissionCount || 0})</p>`;
    } else {
        const errorMsg = (result.data && result.data.message) || result.message || 'خطای نامشخص در بارگذاری داده‌های روند.';
        trendAnalysisDisplayArea.innerHTML = `<p class="no-data-message" style="color:red;">${errorMsg}</p>`;
        displayMessage(trendAnalysisMessageArea, `خطا: ${errorMsg}`, false);
    }
}

function renderTrendChartsInDedicatedArea(aggregatedData, containerElement, isAllClassesTrend) {
    let trendChartsRenderedCount = 0;
    if (!containerElement) {
        console.error("Trend display container not found!");
        return;
    }
    containerElement.innerHTML = ''; // Clear previous content

    // Add an overall title for this dedicated trend section
    const overallTrendTitleEl = document.createElement('h4');
    overallTrendTitleEl.className = 'analytics-overall-title'; // Reuse class if styling is similar
    let titleText = `نمودارهای روند پیشرفت برای: `;
    if (isAllClassesTrend) {
        titleText += `همه کلاس‌ها (تجمیع ماهانه)`;
    } else {
        const selectedOption = trendClassCodeSelect.options[trendClassCodeSelect.selectedIndex];
        titleText += selectedOption.text;
    }
    overallTrendTitleEl.textContent = titleText;
    containerElement.appendChild(overallTrendTitleEl);


    for (const fieldId in aggregatedData) {
        const chartGroupData = aggregatedData[fieldId];
        // We are only interested in fields that have trendData
        if (chartGroupData && chartGroupData.trendData && chartGroupData.trendData.length > 1) {
            const trendChartContainer = document.createElement('div');
            trendChartContainer.className = 'chart-container trend-chart-container-js'; // Specific class for these charts
            
            const chartTitle = document.createElement('h5');
            chartTitle.textContent = `${chartGroupData.questionLabel || fieldId}`; // Title of the question
            trendChartContainer.appendChild(chartTitle);

            const trendCanvas = document.createElement('canvas');
            trendCanvas.className = 'trend-canvas'; 
            trendChartContainer.appendChild(trendCanvas);
            containerElement.appendChild(trendChartContainer);

            const yAxisTrendLabel = chartGroupData.type === 'select_score' ? 'میانگین امتیاز' : 
                                   (chartGroupData.id === 'gr_game_duration_minutes' ? 'مدت زمان (دقیقه)' : 'مقدار');
            
            const trendLabels = chartGroupData.trendData.map(item => item.label || item.date); 
            const trendValues = chartGroupData.trendData.map(item => parseFloat(item.averageValue));

            const trendChartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { 
                        beginAtZero: (chartGroupData.id === 'gr_game_duration_minutes'), // Scores might not start at 0
                        ticks: { precision: 1, font: { family: 'Vazirmatn' } } 
                       },
                    x: { ticks: { autoSkip: true, maxRotation: 45, minRotation: 0, font: { family: 'Vazirmatn' } } }
                },
                plugins: {
                    legend: { display: false }, // Usually not needed for single-line trend
                    tooltip: {
                        titleFont: { family: 'Vazirmatn' },
                        bodyFont: { family: 'Vazirmatn' },
                        callbacks: {
                            title: function(tooltipItems) {
                                const dataIndex = tooltipItems[0].dataIndex;
                                const originalDataPoint = chartGroupData.trendData[dataIndex];
                                return originalDataPoint.label || originalDataPoint.date; 
                            },
                            label: function(context) {
                                return `${yAxisTrendLabel}: ${context.formattedValue}`;
                            }
                        }
                    }
                }
            };

            if (chartGroupData.type === 'select_score') {
                trendChartOptions.scales.y.suggestedMin = 1;
                trendChartOptions.scales.y.suggestedMax = 4;
                trendChartOptions.scales.y.ticks.stepSize = 0.5;
            } else if (chartGroupData.id === 'gr_game_duration_minutes') {
                const maxValue = Math.max(...trendValues, 0); // Ensure 0 if all are negative (though unlikely for duration)
                trendChartOptions.scales.y.ticks.stepSize = Math.max(1, Math.ceil(maxValue / 8)); // Dynamic step, aim for ~8 steps
                trendChartOptions.scales.y.suggestedMin = 0;
            }

            const trendChart = new Chart(trendCanvas, {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [{
                        label: yAxisTrendLabel,
                        data: trendValues,
                        borderColor: (chartGroupData.id === 'gr_game_duration_minutes') ? 'rgb(75, 192, 75)' : 
                                     (chartGroupData.type === 'select_score') ? 'rgb(255, 159, 64)' : 'rgb(54, 162, 235)',
                        backgroundColor: (chartGroupData.id === 'gr_game_duration_minutes') ? 'rgba(75, 192, 75, 0.2)' :
                                         (chartGroupData.type === 'select_score') ? 'rgba(255, 159, 64, 0.2)' : 'rgba(54, 162, 235, 0.2)',
                        tension: 0.1,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: (chartGroupData.id === 'gr_game_duration_minutes') ? 'rgb(75, 192, 75)' : 
                                              (chartGroupData.type === 'select_score') ? 'rgb(255, 159, 64)' : 'rgb(54, 162, 235)',
                    }]
                },
                options: trendChartOptions
            });
            currentCharts.push(trendChart); 
            trendChartsRenderedCount++;
        }
    }
    if (trendChartsRenderedCount === 0) {
        // If an overall title was added, but no charts, add a specific message
        if (containerElement.querySelector('.analytics-overall-title')) {
            containerElement.innerHTML += '<p class="no-data-message">داده کافی برای نمایش نمودار روند برای سوالات این کلاس (یا همه کلاس‌ها) وجود ندارد.</p>';
        } else { // If even the title wasn't added (e.g., error before loop)
            containerElement.innerHTML = '<p class="no-data-message">داده کافی برای نمایش نمودار روند برای سوالات این کلاس (یا همه کلاس‌ها) وجود ندارد.</p>';
        }
    }
}


function generateDistinctColors(count, forBorder = false, isPie = false) {
    const colors = [];
    const baseHues = [210, 30, 120, 270, 60, 0, 330, 180, 240, 45, 300, 150, 20, 80, 100, 140, 160, 200, 220, 260, 320, 340, 25, 50, 75, 110, 130, 170, 190, 230, 250, 270, 290, 310, 350];
    const saturation = isPie ? '75%' : '65%';
    const lightness = isPie ? '60%' : '55%';
    const alpha = forBorder ? '1' : (isPie ? '0.85' : '0.75');

    if (!isPie && count === 1 && !forBorder) return ['hsla(210, 65%, 55%, 0.75)']; 
    if (!isPie && count === 1 && forBorder) return ['hsla(210, 85%, 50%, 1)'];

    for (let i = 0; i < count; i++) { 
        const hue = baseHues[i % baseHues.length]; 
        colors.push(`hsla(${hue}, ${saturation}, ${lightness}, ${alpha})`); 
    }
    return colors;
}

// --- Font for PDF (Base64 encoded Vazirmatn-Regular.ttf) ---
const vazirmatnFont = `AAEAAAAPAIAAAwBwRkZUTZcKZKYAAPvMAAAAHEdERUaQ2+8YAACnPAAABIZHUE9TKDPnJgAAxEAAADeMR1NVQtdQoFkAAKvEAAAYfE9TLzKWAAjtAAABeAAAAGBjbWFw75gdwgAACnQAAALqZ2FzcP//AAMAAKc0AAAACGdseWZYuKocAAARsAAAdThoZWFkJW3ttAAAAPwAAAA2aGhlYRIHEZYAAAE0AAAAJGhtdHirjcZ9AAAB2AAACJxsb2NhirenvgAADWAAAARQbWF4cAKhAOwAAAFYAAAAIG5hbWVABK9GAACG6AAABv5wb3N0YZECnwAAjegAABlMAAEAAAABAADypmgxXw889QALCAAAAAAA3TAZdAAAAADeDYu0/0T8ogvNBu0AAAAIAAIAAAAAAAAAAQAABvQG9AAAC6v/RPzPC80AAQAAAAAAAAAAAAAAAAAAAicAAQAAAicAYwAFAGcABQABAAIAHgAGAAAAZAAAAAMAAwAEBDgBkAAFAQAFMgTOAAAAmwUyBM4AAALLAGQAlgAAAAAAAAAAAAAAAAAAIAGAAAAAAAAACAAAAABVS1dOAMAADf7/BvT9OAAABvQCyAAAAEEgCAAAAJIEUAAAACAAGAdZAI0AAAAAAdAAAAC+AAABzQCNAlUAfwQ6ABcEfQBnBfAAZwUbAFYBcQBtAyMAXwMjAIUDowCOBCEAHQHFAKQC3gCNAc0AjQNcAI0EfQBqBH0ArwR9AFkEfQBMBH0ARwR9AIYEfQBsBH0AQgR9AF4EfQBcAc0AjQHNAI0EFgBABAQAHQQaAHADVQCNB0kAXQUWABEE+QCeBTQAbgUzAJ4EgQCeBHkAngV1AH4FmwCeAj4AvQRuAEEFGACeBDkAngbrAJ4FoQCeBWgAaAT9AJ4FaABoBTcAmwTYAFEE5wAvBVIAkgUBABIHGQArBOwAHgTjABEE0gBRAfgAkgNcAI4B+AACA28AQQKAAAAEPABHBG0AhwQZAEwEbQBYBCwASgKxACgEegBaBG8AhQHnAH8B7v99BA4AiQHnAJYHEQCCBG8AhgRuAEYEbQCHBGwAWALXAIkEBgBLAp8AGwRvAIMD9wAXBe4ANwPzACID+AAUA/MATQKWACwBzACwApYAOQVIAIAAZQAAAckAkQZkAGkEfACOBFYAewZnAGkDHACMBD4ATQS5AJcEfACNA9IAHQSIAEMBxgCNAc0AjQNVAI0DJQCNAZb/qAGBAFkDsABYAZYAWwXkAGsBlgCNB3oAjQPlAI0HegCNB3oAjQRRAI0EYgCNBGIAjQMZAFkDGQBZAcIADwHCAA8I1QCNCNUAjQkWAI0JFgCNBkcAjQZHAI0EiQCNBIkAjQFQ/+EHegCNBeMAjQeMAJQEyQCNBXEAjQS1AI0D5QCNA7AAWAXkAI0F5ACNAAAA4AAAALYAAADgAAAAtgAAALYAAAC2AAAAtAAAAIEAAAC2AAAAuQAAALkAAAAkApsAxAHJAI0EEACNBbcAjQSlAFgFIgBvA9oAYAQvAI0ELwCOBBEAbwNYAB4ChgAyAVsAIgTCAI0HegCNBeMAjQAAABYHegCNBIEAjQHCAA8HegCNB3oAjQioAI0IqACNBLUAjQPlAI0D5QCNA+UAjQPlAI0DsABYBeQAjQVFAI0FRQCNAc0AjQPlAI0DyQB/AckAjQQQAI0FtwCNBRkAiQW7AG8EiwCNBC8AjQQvAI4EEQBvANQAAAB1AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAHFAL0BxQCkAsoAvQLKAKQCygCkAmYAjAVHAI0BXwB3ApoAnALiAI0C4gCNBPQAdQSSAF8FNQCtBNcANQQeAI0EygEQBCMAPAiRAHYCN/99BJMAZASOAIoDpQBAA6cAYAMjAF8DIwCFCeUA5QRhAI0AAAAAAYEAjQIXAI0CCwCNAgoAjQIKAFkCFwCNAhf/qAgOAI0CZP/hAwX/4QHO/+ECcv/hCA4AjQJk/8wDBf/hAc7/zwJy/+EIDgCNAmT/UwMF/+EBzv9UAnL/4QgOAI0CZP/hAwX/4QHO/+ECcv/hCA4AjQJk/+EDBf/hAc7/4QJy/+EEgACNBCD/4QPm/+EErwCNBCD/4QPm/+EEgACNBCD/4QPm/+EEgACNBCD/4QPm/+EDPgBZA7AAZQM+AFkDsABlAkAADwJAAA8CQAAPCW0AjQoRAI0Gbf/hBxH/4QXW/+EGe//hCW0AjQoRAI0Gbf/hBxH/4QXW/+EGe//hCSgAjQX3/+EF4P/hCSgAjQX3/+EF4P/hBmgAjQYB/+EF5P/hBmgAjQYB/+EF5P/hBLUAjQP+/+EDeP/hBLUAjQP+/+EDeP/hB+kAjQQC/+ED1//hB+kAjQQC/+ED1//hB+kAjQQC/+ED1//h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DSYNOg1eDX4NpA28DeYN9A4eDlQOVA5mDsYO6A74D2APkg+yD9oP/hAYEEYQVhBsEJgQxBDQENwQ6BD0EQARDhEaESYRMhE+EXgRrhG6EdYR4hH0EgASUhJeEqoSthLiEu4TJBMwE0ATTBNYE54TxhQCFA4UOhRmFKAUrBS6FQwVFhUkFVIVXBWOFboVyhX0Ff4WCBYWFiQWUhaQFsQW/BcMFyoXSBdwF4wXmheoF9IX+hg2GEQYUBiOGJoY0BjcGRQZIBlIGVQZXBloGXAZfBmEGagZtBm8GcQZ9hn+GgYaDhpGGpQaxhrOGtYa3hreGt4a3hreGt4a3hreGvAa+hsYGyYbMhtAG1AbWBtgG3QbiBuwG/YcChwoHDYcRhxcHLYc3B0oHUwdZh2CHaQdxB4eHj4ePh5MHmYeeB6KHpYeoh6uHugfDh80H1AfbB94H4QfkB+cH6gftB/AH8wf2B/kH/Af/CAIIBQgICAsIDggRCBQIFwgoCCsILgg/iEKIRYhViF8IZohpiGyIb4h4iIQIhwiKCJKIlYiYiLEIygjgiPcJCQkbCR4JIQkkCScJKgktCUIJUIldCWAJYwlmCXOJgQmMiY+JkomViacJswm6ib2JwInDicaJyYnMic+J0onVieUJ8Yn8Cg0KEAoTChYKKoosijeKSgpZiluKXophimSKc4p/CoYKmYqsirsKvgrBCsQKxwrKCtYK5Yr7CwwLH4siiyWLJ4spiyyLL4s8iz+LQotPi1KLVYtYi1uLXothi2SLZ4tqi22Ldot4i4KLhIuGi4iLiouUi5eLmYumC6kLrAuvC7ILtQu4C8EL04vWi9qL3ovhi+WL6Yvsi++L84v3i/qL/owCjAWMCIwMjBCME4wXjBuMHowhjCWMKYwsjDCMNIw3jEsMYYxkjGeMaoxtjIiMpgypDKwMrwyyDMIM1AzXDNoM3QzgDPkNFA0XDRoNHg0iDSYNKg1EjWENgw2GDYoNjg2RDZUNmQ2cDZ8Now2mDaoNrg2yDbUNuA28DcANww3HDcsNzg3QDdIN1A3WDdgN2g3cDd8N4Q3jDeWN6A3qje0N7w3xDfMN9Y34DfqN/Q3/DgEOAw4FDgcOCQ4LDg0ODw4RDhMOFQ4XDhkOGw4dDh8OIQ4jDiUOJ44rDi6OMY40jjeOOg48jkAOQ45HjkuOT45Tjl4OYI5jjma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APwAABAHAg3+/QAL////4f6TAi0C8xAiAP0AABAGAg2+CwAA//8Ajf3dCC4C8xAiAPkAABAHAhECi/+I////U/3dAoMC8xAiAPoAABAHAhH+0v+I////4f3dAyMC8xAiAPsAABAGAhGZiAAA////VP3dAYoC8xAiAPwAABAHAhH+0/+I////4f3dAi0C8xAiAP0AABAGAhGUiAAA//8AjQAACC4C8xAiAPkAABAHAg8CYP+N////4QAAAoMEXBAiAPoAABAHAg//UQEU////4QAAAyMEXBAiAPsAABAHAg//1QEU////4QAAAakEXBAiAPwAABAHAg//UQEU////4QAAAlEEXBAiAP0AABAHAg//+QEU//8AjQAACC4DkhAiAPkAABAHAhMCZQAb////4QAAAoMFFxAiAPoAABAHAhP/VAGg////4QAAAyMFFxAiAPsAABAHAhP/1wGg////4QAAAacFFxAiAPwAABAHAhP/VAGg////4QAAAlAFFxAiAP0AABAHAhP//QGgAAMAjf2bBKADFAAFACcAKwAAJTczFwcjAC4BNTQ+ATMhNTQuASMhNyEyHgEVESEiDgEVFB4BOwEHIxMzFSMDiWOUICDl/hK1a2u1awFIPGU7/pcqAT9qtWr+CztlOztlO/I6uNG0tJAeV1f9m2q1a2u0atw7ZTuvarVr/nY7ZTs7ZjuuAZ6fAP///+H+mQQ/AxMQIgEZAAAQBwINALMAEf///+H+mQOhAxMQIgEaAAAQBwINALkAEQADAI39mgTOAxMABAAlACsAACUzFwcjAC4BNTQ+ATMhNTQuASMhNyEyHgEVESEiDgEVFB4BOwEHAREHNSE1A9XaHx/c/dm1amq1awF2O2U7/pYsAT5rtWr92zxlOztlPDk5AZ2g/vauWFb9mmq2a2u0atw7ZTuuarRr/nY7ZTs8ZTuvAb/+2y+/lQAA////4f3jBD8DExAiARkAABAHAhEAif+O////4f3jA6EDExAiARoAABAHAhEAjv+OAAIAjf2bBJ8DEwAFACcAACU3MxcHIwAuATU0PgEzITU0LgEjITchMh4BFREhIg4BFRQeATMhByEDiWOUHx/l/hK1a2u1agFHO2U7/pYsAT5rtGr+CztlOjpkPAIIO/4zlxdXV/2barVra7Rq3DtlO65qtGv+djtlOzxkO68AAAAAAv/h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AHIAAFJlZ3VsYXIAAEMAbwBwAHkAcgBpAGcAaAB0ACAAKABjACkAIAAyADAAMgAxACAAYgB5ACAAdwB3AHcALgBmAG8AbgB0AGkAcgBhAG4ALgBjAG8AbQAgACgATQBvAHMAbABlAG0AIABFAGIAcgBhAGgAaQBtAGkAKQAuACAAQQBsAGwAIAByAGkAZwBoAHQAcwAgAHIAZQBzAGUAcgB2AGUAZAAuAAAATQBvAHIAYQBiAGIAYQAgAGkAcwAgAGEAIAB0AHIAYQBkAGUAbQBhAHIAawAgAG8AZgAgAHcAdwB3AC4AZgBvAG4AdABpAHIAYQBuAC4AYwBvAG0AIAAoAE0AbwBzAGwAZQBtACAARQBiAHIAYQBoAGkAbQBpACkALgAAAEgAYQBzAHMAYQBuACAATQBhAG4AegBvAG8AcgBpAAAAAAACAAAAAAAAAAAAXQAAAAAAAAAAAAAAAAAAAAAAAAAAAicAAAABAAIAAwAEAAUABgAHAAgACQAKAAsADAANAA4ADwAQABEAEgATABQAFQAWABcAGAAZABoAGwAcAB0AHgAfACAAIQAiACMAJAAlACYAJwAoACkAKgArACwALQAuAC8AMAAxADIAMwA0ADUANgA3ADgAOQA6ADsAPAA9AD4APwBAAEEAQgBEAEUARgBHAEgASQBKAEsATABNAE4ATwBQAFEAUgBTAFQAVQBWAFcAWABZAFoAWwBcAF0AXgBfAGAAYQECAOgAiwCpAKQAigCDAJMBAwCqAPAAuAEEAQUBBgEHAQgBCQEKAQsBDAENAQ4BDwEQAREBEgETARQBFQEWARcBGAEZARoBGwEcAR0BHgEfASABIQEiASMBJAElASYBJwEoASkBKgErASwBLQEuAS8BMAExATIBMwE0ATUBNgE3ATgBOQE6ATsBPAE9AT4BPwFAAUEBQgFDAUQBRQFGAUcBSAFJAUoBSwFMAU0BTgFPAVABUQFSAVMBVAFVAVYBVwFYAVkBWgFbAVwBXQFeAV8BYAFhAWIBYwFkAWUBZgFnAWgBaQFqAWsAtgC3ALQAtQDFAIcAqwFsAW0AvgC/AIwAmACaAJkA7wFuAKUAkgCcAKcAjwCUAJUBbwFwAXEBcgFzAXQBdQF2AXcBeAF5AXoBewF8AX0BfgF/AYABgQGCAYMBhAGFAYYBhwGIAYkBigGLAYwBjQGOAY8BkAGRAZIBkwGUAZUBlgGXAZgBmQGaAZsBnAGdAZ4BnwGgAaEBogGjAaQBpQGmAacBqAGpAaoBqwGsAa0BrgGvAbABsQGyAbMBtAG1AbYBtwG4AbkBugG7AbwBvQG+Ab8BwAHBAcIBwwHEAcUBxgHHAcgByQHKAcsBzAHNAc4BzwHQAdEB0gHTAdQB1QHWAdcB2AHZAdoB2wHcAd0B3gHfAeAB4QHiAeMB5AHlAeYB5wHoAekB6gHrAewB7QHuAe8B8AHxAfIB8wH0AfUB9gH3AfgB+QH6AfsB/AH9Af4B/wIAAgECAgIDAgQCBQIGAgcCCAIJAgoCCwIMAg0CDgIPAhACEQISAhMCFAIVAhYCFwIYAhkCGgIbAhwCHQIeAh8CIAIhAiICIwIkAiUCJgInAigCKQIqAisCLAItAi4CLwIwAjECMgIzAjQCNQI2AjcCOAI5AjoCOwI8Aj0CPgI/AkACQQJCAkMCRAJFAkYCRwJIAkkCSgJLAkwCTQJOAk8CUAJRAlICUwJUAlUCVgJXAlgCWQJaAlsCXAJdAl4CXwJgAmECYgJjAmQCZQJmAmcCaAJpAmoCawJsAm0CbgJvAnACcQJyAnMCdAJ1AnYCdwJ4AnkCegJ7AnwCfQJ+An8CgAKBAoICgwKEAoUChgKHAogCiQKKAosCjAKNAo4CjwKQApECkgKTApQClQKWApcCmAKZApoCmwKcAp0CngKfAqACoQKiAqMCpAKlAqYCpwKoB3VuaTAwQTAHdW5pMDBCNQd1bmkwNjBDB3VuaTA2MUIHdW5pMDYxRgd1bmkwNjIxB3VuaTA2MjIHdW5pMDYyMwd1bmkwNjI0B3VuaTA2MjUHdW5pMDYyNgd1bmkwNjI3B3VuaTA2MjgHdW5pMDYyOQd1bmkwNjJBB3VuaTA2MkIHdW5pMDYyQwd1bmkwNjJEB3VuaTA2MkUHdW5pMDYyRgd1bmkwNjMwB3VuaTA2MzEHdW5pMDYzMgd1bmkwNjMzB3VuaTA2MzQHdW5pMDYzNQd1bmkwNjM2B3VuaTA2MzcHdW5pMDYzOAd1bmkwNjM5B3VuaTA2M0EHdW5pMDY0MAd1bmkwNjQxB3VuaTA2NDIHdW5pMDY0Mwd1bmkwNjQ0B3VuaTA2NDUHdW5pMDY0Ngd1bmkwNjQ3B3VuaTA2NDgHdW5pMDY0OQd1bmkwNjRBB3VuaTA2NEIHdW5pMDY0Qwd1bmkwNjREB3VuaTA2NEUHdW5pMDY0Rgd1bmkwNjUwB3VuaTA2NTEHdW5pMDY1Mgd1bmkwNjUzB3VuaTA2NTQHdW5pMDY1NQd1bmkwNjU2B3VuaTA2NjAHdW5pMDY2MQd1bmkwNjYyB3VuaTA2NjMHdW5pMDY2NAd1bmkwNjY1B3VuaTA2NjYHdW5pMDY2Nwd1bmkwNjY4B3VuaTA2NjkHdW5pMDY2QQd1bmkwNjZCB3VuaTA2NkMHdW5pMDY2RAd1bmkwNjZFB3VuaTA2NkYHdW5pMDY3MAd1bmkwNjdFB3VuaTA2ODYHdW5pMDY5OAd1bmkwNkExB3VuaTA2QTQHdW5pMDZBOQd1bmkwNkFGB3VuaTA2QkEHdW5pMDZDMAd1bmkwNkMxB3VuaTA2QzIHdW5pMDZDMwd1bmkwNkM3B3VuaTA2Q0MHdW5pMDZEMgd1bmkwNkQzB3VuaTA2RDQHdW5pMDZENQd1bmkwNkYwB3VuaTA2RjEHdW5pMDZGMgd1bmkwNkYzB3VuaTA2RjQHdW5pMDZGNQd1bmkwNkY2B3VuaTA2RjcHdW5pMDZGOAd1bmkwNkY5B3VuaTIwMDkHdW5pMjAwQQd1bmkyMDBCB3VuaTIwMEMHdW5pMjAwRAd1bmkyMDBFB3VuaTIwMEYGbWludXRlBnNlY29uZAd1bmkyMjE1B3VuaUZEM0UHdW5pRkQzRgd1bmlGREZDC3VuaTA2NDQwNjI3B3VuaUZFRkYMdW5pMDYyNy5zczAzDHVuaTA2MjcuZmluYRF1bmkwNjI3LmZpbmEuc3MwMhF1bmkwNjI3LmZpbmEuc3MwMwx1bmkwNjIzLmZpbmEMdW5pMDYyNS5maW5hDHVuaTA2MjIuZmluYQx1bmkwNjZFLmZpbmEMdW5pMDY2RS5tZWRpEXVuaTA2NkUubWVkaS5sb25nDHVuaTA2NkUuaW5pdBF1bmkwNjZFLmluaXQubG9uZwx1bmkwNjI4LmZpbmEMdW5pMDYyOC5tZWRpEXVuaTA2MjgubWVkaS5sb25nDHVuaTA2MjguaW5pdBF1bmkwNjI4LmluaXQubG9uZwx1bmkwNjdFLmZpbmEMdW5pMDY3RS5tZWRpEXVuaTA2N0UubWVkaS5sb25nDHVuaTA2N0UuaW5pdBF1bmkwNjdFLmluaXQubG9uZwx1bmkwNjJBLmZpbmEMdW5pMDYyQS5tZWRpEXVuaTA2MkEubWVkaS5sb25nDHVuaTA2MkEuaW5pdBF1bmkwNjJBLmluaXQubG9uZwx1bmkwNjJCLmZpbmEMdW5pMDYyQi5tZWRpEXVuaTA2MkIubWVkaS5sb25nDHVuaTA2MkIuaW5pdBF1bmkwNjJCLmluaXQubG9uZwx1bmkwNjJDLmZpbmEMdW5pMDYyQy5tZWRpDHVuaTA2MkMuaW5pdAx1bmkwNjg2LmZpbmEMdW5pMDY4Ni5tZWRpDHVuaTA2ODYuaW5pdAx1bmkwNjJELmZpbmEMdW5pMDYyRC5tZWRpDHVuaTA2MkQuaW5pdAx1bmkwNjJFLmZpbmEMdW5pMDYyRS5tZWRpDHVuaTA2MkUuaW5pdAx1bmkwNjJGLmZpbmERdW5pMDYyRi5maW5hLnNzMDMMdW5pMDYzMC5maW5hEXVuaTA2MzAuZmluYS5zczAzDHVuaTA2MzEuZmluYQx1bmkwNjMyLmZpbmEMdW5pMDY5OC5maW5hDHVuaTA2MzMuZmluYRF1bmkwNjMzLmZpbmEubG9uZwx1bmkwNjMzLm1lZGkRdW5pMDYzMy5tZWRpLmxvbmcMdW5pMDYzMy5pbml0EXVuaTA2MzMuaW5pdC5sb25nDHVuaTA2MzQuZmluYRF1bmkwNjM0LmZpbmEubG9uZwx1bmkwNjM0Lm1lZGkRdW5pMDYzNC5tZWRpLmxvbmcMdW5pMDYzNC5pbml0EXVuaTA2MzQuaW5pdC5sb25nDHVuaTA2MzUuZmluYQx1bmkwNjM1Lm1lZGkMdW5pMDYzNS5pbml0DHVuaTA2MzYuZmluYQx1bmkwNjM2Lm1lZGkMdW5pMDYzNi5pbml0DHVuaTA2MzcuZmluYQx1bmkwNjM3Lm1lZGkMdW5pMDYzNy5pbml0DHVuaTA2MzguZmluYQx1bmkwNjM4Lm1lZGkMdW5pMDYzOC5pbml0DHVuaTA2MzkuZmluYQx1bmkwNjM5Lm1lZGkMdW5pMDYzOS5pbml0DHVuaTA2M0EuZmluYQx1bmkwNjNBLm1lZGkMdW5pMDYzQS5pbml0DHVuaTA2NDEuZmluYQx1bmkwNjQxLm1lZGkMdW5pMDY0MS5pbml0DHVuaTA2QTQuZmluYQx1bmkwNkE0Lm1lZGkMdW5pMDZBNC5pbml0DHVuaTA2QTEuZmluYQx1bmkwNkExLm1lZGkMdW5pMDZBMS5pbml0DHVuaTA2NkYuZmluYQx1bmkwNjQyLmZpbmEMdW5pMDY0Mi5tZWRpDHVuaTA2NDIuaW5pdAx1bmkwNjQzLmZpbmEMdW5pMDY0My5tZWRpDHVuaTA2NDMuaW5pdAx1bmkwNkE5LmZpbmEMdW5pMDZBOS5tZWRpDHVuaTA2QTkuaW5pdAx1bmkwNkFGLmZpbmEMdW5pMDZBRi5tZWRpDHVuaTA2QUYuaW5pdAx1bmkwNjQ0LmZpbmEMdW5pMDY0NC5tZWRpDHVuaTA2NDQuaW5pdAx1bmkwNjQ1LmZpbmEMdW5pMDY0NS5tZWRpDHVuaTA2NDUuaW5pdAx1bmkwNjQ2LmZpbmEMdW5pMDY0Ni5tZWRpEXVuaTA2NDYubWVkaS5sb25nDHVuaTA2NDYuaW5pdBF1bmkwNjQ2LmluaXQubG9uZwx1bmkwNkJBLmZpbmEMdW5pMDY0Ny5maW5hDHVuaTA2NDcubWVkaRF1bmkwNjQ3Lm1lZGkuc3MwMwx1bmkwNjQ3LmluaXQMdW5pMDZDMC5maW5hDHVuaTA2QzIuZmluYQx1bmkwNkJFLm1lZGkMdW5pMDZCRS5pbml0DHVuaTA2MjkuZmluYQx1bmkwNkMzLmZpbmEMdW5pMDY0OC5maW5hDHVuaTA2MjQuZmluYQx1bmkwNkM3LmZpbmEMdW5pMDY0OS5maW5hDHVuaTA2NEEuZmluYQx1bmkwNjRBLm1lZGkRdW5pMDY0QS5tZWRpLmxvbmcMdW5pMDY0QS5pbml0EXVuaTA2NEEuaW5pdC5sb25nDHVuaTA2MjYuZmluYQx1bmkwNjI2Lm1lZGkRdW5pMDYyNi5tZWRpLmxvbmcMdW5pMDYyNi5pbml0EXVuaTA2MjYuaW5pdC5sb25nDHVuaTA2Q0Muc3MwMwx1bmkwNkNDLmZpbmERdW5pMDZDQy5maW5hLnNzMDMMdW5pMDZDQy5tZWRpEXVuaTA2Q0MubWVkaS5sb25nDHVuaTA2Q0MuaW5pdBF1bmkwNkNDLmluaXQubG9uZwx1bmkwNkQyLmZpbmEMdW5pMDZEMy5maW5hDHVuaTA2RDUuZmluYRB1bmkwNjQ0MDYyNy5maW5hC3VuaTA2NDQwNjIzEHVuaTA2NDQwNjIzLmZpbmELdW5pMDY0NDA2MjUQdW5pMDY0NDA2MjUuZmluYQt1bmkwNjQ0MDYyMhB1bmkwNjQ0MDYyMi5maW5hEHVuaTA2NkUwNjMxLmZpbmEQdW5pMDY2RTA2NDkuZmluYRB1bmkwNjI4MDYzMS5maW5hEHVuaTA2MjgwNjMyLmZpbmEQdW5pMDYyODA2OTguZmluYRB1bmkwNjI4MDY0OS5maW5hEHVuaTA2MjgwNjRBLmZpbmEQdW5pMDYyODA2MjYuZmluYRB1bmkwNjI4MDZDQy5maW5hEHVuaTA2N0UwNjMxLmZpbmEQdW5pMDY3RTA2MzIuZmluYRB1bmkwNjdFMDY5OC5maW5hEHVuaTA2N0UwNjQ5LmZpbmEQdW5pMDY3RTA2NEEuZmluYRB1bmkwNjdFMDYyNi5maW5hEHVuaTA2N0UwNkNDLmZpbmEQdW5pMDYyQTA2MzEuZmluYRB1bmkwNjJBMDYzMi5maW5hEHVuaTA2MkEwNjk4LmZpbmEQdW5pMDYyQTA2NDkuZmluYRB1bmkwNjJBMDY0QS5maW5hEHVuaTA2MkEwNjI2LmZpbmEQdW5pMDYyQTA2Q0MuZmluYRB1bmkwNjJCMDYzMS5maW5hEHVuaTA2MkIwNjMyLmZpbmEQdW5pMDYyQjA2OTguZmluYRB1bmkwNjJCMDY0OS5maW5hEHVuaTA2MkIwNjRBLmZpbmEQdW5pMDYyQjA2MjYuZmluYRB1bmkwNjJCMDZDQy5maW5hC3VuaTA2MzMwNjMxEHVuaTA2MzMwNjMxLmZpbmELdW5pMDYzMzA2MzIQdW5pMDYzMzA2MzIuZmluYQt1bmkwNjMzMDY5OBB1bmkwNjMzMDY5OC5maW5hC3VuaTA2MzMwNkNDEHVuaTA2MzMwNkNDLmZpbmELdW5pMDYzNDA2MzEUdW5pMDYzNDA2MzEuZmluYS4wMDELdW5pMDYzNDA2Q0MQdW5pMDYzNDA2Q0MuZmluYQt1bmkwNjM1MDYzMRB1bmkwNjM1MDYzMS5maW5hC3VuaTA2MzUwNjMyEHVuaTA2MzUwNjMyLmZpbmELdW5pMDYzNTA2OTgQdW5pMDYzNTA2OTguZmluYQt1bmkwNjM1MDZDQxB1bmkwNjM1MDZDQy5maW5hC3VuaTA2MzYwNjMxEHVuaTA2MzYwNjMxLmZpbmELdW5pMDYzNjA2MzIQdW5pMDYzNjA2MzIuZmluYQt1bmkwNjM2MDY5OBB1bmkwNjM2MDY5OC5maW5hC3VuaTA2MzYwNkNDEHVuaTA2MzYwNkNDLmZpbmEPdW5pMDY0NDA2NDQwNjQ3EHVuaTA2NDYwNjMxLmZpbmEQdW5pMDY0NjA2MzIuZmluYRB1bmkwNjQ2MDY5OC5maW5hEHVuaTA2NDYwNjQ5LmZpbmEQdW5pMDY0NjA2NEEuZmluYRB1bmkwNjQ2MDYyNi5maW5hEHVuaTA2NDYwNkNDLmZpbmEQdW5pMDY0QTA2MzEuZmluYRB1bmkwNjRBMDYzMi5maW5hEHVuaTA2NEEwNjQ5LmZpbmEQdW5pMDY0QTA2NEEuZmluYRB1bmkwNjRBMDY5OC5maW5hEHVuaTA2NEEwNjI2LmZpbmEQdW5pMDY0QTA2Q0MuZmluYRB1bmkwNjI2MDYzMS5maW5hEHVuaTA2MjYwNjMyLmZpbmEQdW5pMDYyNjA2OTguZmluYRB1bmkwNjI2MDY0OS5maW5hEHVuaTA2MjYwNjRBLmZpbmEQdW5pMDYyNjA2MjYuZmluYRB1bmkwNjI2MDZDQy5maW5hEHVuaTA2Q0MwNjMxLmZpbmEQdW5pMDZDQzA2MzIuZmluYRB1bmkwNkNDMDY5OC5maW5hEHVuaTA2Q0MwNjQ5LmZpbmEQdW5pMDZDQzA2NEEuZmluYRB1bmkwNkNDMDYyNi5maW5hEHVuaTA2Q0MwNkNDLmZpbmEHdW5pRkRGMgx1bmkwNjY0LnNzMDIMdW5pMDY2NS5zczAyDHVuaTA2NjYuc3MwMgx1bmkwNkYwLnNzMDIMdW5pMDZGMS5zczAyDHVuaTA2RjIuc3MwMgx1bmkwNkYzLnNzMDIMdW5pMDZGNC5zczAyDHVuaTA2RjUuc3MwMgx1bmkwNkY2LnNzMDIMdW5pMDZGNy5zczAyDHVuaTA2Rjguc3MwMgx1bmkwNkY5LnNzMDIJemVyby5zczAxCG9uZS5zczAxCHR3by5zczAxCnRocmVlLnNzMDEJZm91ci5zczAxCWZpdmUuc3MwMQhzaXguc3MwMQpzZXZlbi5zczAxCmVpZ2h0LnNzMDEJbmluZS5zczAxCXplcm8uc3MwMghvbmUuc3MwMgh0d28uc3MwMgp0aHJlZS5zczAyCWZvdXIuc3MwMglmaXZlLnNzMDIIc2l4LnNzMDIKc2V2ZW4uc3MwMgplaWdodC5zczAyCW5pbmUuc3MwMgpwZXJpb2QuMDAxCmh5cGhlbi4wMDEKaHlwaGVuLjAwMg51bmRlcnNjb3JlLjAwMQ51bmRlcnNjb3JlLjAwMgd1bmlGQkIyC2RvdGJlbG93LWFyDGRvdGNlbnRlci1hchl0d29kb3RzaG9yaXpvbnRhbGFib3ZlLWFyGXR3b2RvdHNob3Jpem9udGFsYmVsb3ctYXIVdGhyZWVkb3RzZG93bmJlbG93LWFyFnRocmVlZG90c2Rvd25jZW50ZXItYXITdGhyZWVkb3RzdXBhYm92ZS1hchJnYWZzYXJrYXNoYWJvdmUtYXILdW5pMDY1NC4wMDELdW5pMDY1NS4wMDELdW5pMDY1NDA2NEYLdW5pMDY1NDA2NEMLdW5pMDY1NDA2NEULdW5pMDY1NDA2NEILdW5pMDY1NDA2NTILdW5pMDY1NTA2NTALdW5pMDY1NTA2NEQLdW5pMDY1MTA2NEILdW5pMDY1MTA2NEMLdW5pMDY1MTA2NEQLdW5pMDY1MTA2NEULdW5pMDY1MTA2NEYLdW5pMDY1MTA2NTALdW5pMDY1MTA2NzALdW5pMDY1My4wMDEJc2FyZXlhLWFyAAAAAf//AAIAAQACAA4AAABOBAQEUAACAAoAAQCUAAEAlQCgAAMAoQCwAAEAsQCxAAMAsgDvAAEA8ADwAAIA8QGGAAEBhwHlAAIB5gILAAECDAImAAMDpgBdAL4AxgDOANYA3gDmAO4A9gD+AQYBDgEWAR4BJgEuATYBPgFGAU4BVgFeAWYBbgF2AX4BhgGOAZYBngGmAa4BtgG+AcYBzgHWAd4B5gHuAfYB/gIGAg4CFgIeAiYCLgI2Aj4CRgJOAlYCXgJmAm4CdgJ+AoYCjgKWAp4CpgKuArYCvgLGAs4C1gLeAuYC7gL2Av4DBgMOAxYDHgMmAy4DNgM+A0YDTgNWA14DZgNuA3YDfgOGA44DlgOeAAEABAABAikAAQAEAAECEAABAAQAAQIpAAEABAABAkUAAQAEAAECKQABAAQAAQIZAAEABAABAikAAQAEAAECBQABAAQAAQVFAAEABAABAgUAAQAEAAECBQABAAQAAQIFAAEABAABBUUAAQAEAAEFRQABAAQAAQVFAAEABAABBUUAAQAEAAECBQABAAQAAQIFAAEABAABAgUAAQAEAAEFRQABAAQAAQVFAAEABAABBUUAAQAEAAEFRQABAAQAAQH9AAEABAABAgUAAQAEAAECBQABAAQAAQVFAAEABAABBUUAAQAEAAEFRQABAAQAAQVFAAEABAABAfkAAQAEAAEB+QABAAQAAQIJAAEABAABBUUAAQAEAAEFRQABAAQAAQVFAAEABAABBUUAAQAEAAEDJQABAAQAAQMlAAEABAABAyUAAQAEAAEDJQABAAQAAQMlAAEABAABAyUAAQAEAAEGVAABAAQAAQZUAAEABAABAyUAAQAEAAEDJQABAAQAAQZEAAEABAABBWwAAQAEAAECkAABAAQAAQKQAAEABAABApAAAQAEAAECkAABAAQAAQKQAAEABAABApAAAQAEAAEGaAABAAQAAQZoAAEABAABApAAAQAEAAECkAABAAQAAQKQAAEABAABApAAAQAEAAECkAABAAQAAQKQAAEABAABBmgAAQAEAAEGaAABAAQAAQIJAAEABAABAg0AAQAEAAECDQABAAQAAQVFAAEABAABBUUAAQAEAAEFRQABAAQAAQVFAAEABAABAgUAAQAEAAECBQABAAQAAQVFAAEABAABBUUAAQAEAAECCQABAAQAAQVFAAEABAABBUUAAQAEAAECCQABAAQAAQIJAAEABAABAg0AAQAEAAEFRQABAAQAAQVFAAEABAABBUUAAQAEAAEFRQABAAQAAQIFAAEABAABAgUAAQAEAAECCQABAAQAAQVFAAEABAABBUUAAQAEAAEFRQABAAQAAQVFAAIAAgGHAccAAAHJAeQAQQACAAwAlQCWAAEAlwCXAAIAmACZAAEAmgCaAAIAmwCeAAEAnwCgAAIAsQCxAAECFQIVAAECFgIWAAICFwIbAAECHAIdAAICHgIkAAEAAQABAAAACAACAAcAlQCWAAAAmACZAAIAmwCeAAQAsQCxAAgCFQIVAAkCFwIbAAoCHgIkAA8AAAABAAAACgBYAPIAAkRGTFQADmFyYWIALgAEAAAAAP//AAsAAAABAAIAAwAEAAUABgAIAAkACgALAAQAAAAA//8ACwAAAAEAAgADAAQABQAGAAcACQAKAAsADGFhbHQASmNhbHQAUmNjbXAAWGRsaWcAXmZpbmEAZGluaXQAam1l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AAEAAQGCAAEADAD/AQQBCQEOASUBJwErAS0BYAF0AXkBgAADAAAAAQASAAEAGAABAAAADgABAAEA/wABAAwA/wEEAQkBDgElAScBKwEtAWABdAF5AYAAAwAAAAEAEgABABgAAQAAAA4AAQABAQQAAQAMAP8BBAEJAQ4BJQEnASsBLQFgAXQBeQGAAAMAAAABABIAAQAYAAEAAAAOAAEAAQEJAAEADAD/AQQBCQEOASUBJwErAS0BYAF0AXkBgAADAAAAAQASAAEAGAABAAAADgABAAEBDgABAAwA/wEEAQkBDgElAScBKwEtAWABdAF5AYAAAwAAAAEAEgABABgAAQAAAA4AAQABAScAAQAMAP8BBAEJAQ4BJQEnASsBLQFgAXQBeQGAAAMAAAABABIAAQAYAAEAAAAOAAEAAQEtAAEADAD/AQQBCQEOASUBJwErAS0BYAF0AXkBgAADAAAAAQASAAEAGAABAAAADgABAAEBYAABAAwA/wEEAQkBDgElAScBKwEtAWABdAF5AYAAAwAAAAEAEgABABgAAQAAAA4AAQABAXQAAQAMAP8BBAEJAQ4BJQEnASsBLQFgAXQBeQGAAAMAAAABABIAAQAYAAEAAAAOAAEAAQF5AAEADAD/AQQBCQEOASUBJwErAS0BYAF0AXkBgAADAAAAAQASAAEAGAABAAAADgABAAEBgAABAAwA/wEEAQkBDgElAScBKwEtAWABdAF5AYAAAQAGAeAAAgABABMAHAAAAAIANgAYAf0B/gH/AgACAQICAgMCBAIFAgYB5gHnAegB6QHqAesB7AHtAe4B7wHwAfEB8gD0AAIABAATABwAAAClAKcACgDEAM0ADQDzAPMAFwACABQABwDyAX0A9QEfASEBZwF/AAEABwB2AL8A8wEeASABZgF+AAEABgABAAEAFAD/AQEBBAEGAQkBCwEOARABJwEpAS0BLwFgAWIBdAF2AXkBewGAAYIAAQAAAAoAOABqAAJERkxUAA5hcmFiAB4ABAAAAAD//wADAAAAAQACAAQAAAAA//8AAwAAAAEAAgADa2VybgAUbWFyawAgbWttawAoAAAABAAAAAEAAgADAAAAAgAEAAUAAAADAAYABwAIAAkAFAAeACYALgA6AEIASgBUAF4AAgAIAAIAVABsAAIACAABAPgAAgAIAAEHZAACAAgAAwd2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BFgEWARYBFgEWARYBFgEWARYBJIEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEmgU4BdAEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBJIEkgSSBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgABQBxAA0ADQByACIAIgB0ACIAIgB2ACIAIgCOAA0ADQAEAHIADQANAHQADQANAHYADQANAI4ADQANAAEAAwAgACAAGgADAFEAUQBxAAYABgByABwAHABzABAAEAB0ABwAHAB1AB0AHQB2ABwAHAB7AB0AHQB8AB0AHQB9AB0AHQCIAB0AHQCJAB0AHQCMAB0AHQCNAAYABgCOAD0APQCQAB0AHQCSABAAEACTAB0AHQCUAB0AHQCwAB0AHQCzAB0AHQC5AB0AHQC+ABAAEAC/AB0AHQFbAAYABgF9AB0AHQAZAHEABgAGAHIAHAAcAHMAEAAQAHQAHAAcAHUAHQAdAHYAHAAcAHsAHQAdAHwAHQAdAH0AHQAdAIgAHQAdAIkAHQAdAIwAHQAdAI0ABgAGAI4AHQAdAJAAHQAdAJIAEAAQAJMAHQAdAJQAHQAdALAAHQAdALMAHQAdALkAHQAdAL4AEAAQAL8AHQAdAVsABgAGAX0AHQAdABkAcQAKAAoAcgANAA0AcwAQABAAdAANAA0AdQAdAB0AdgANAA0AewAdAB0AfAAdAB0AfQAdAB0AiAAdAB0AiQAdAB0AjAAdAB0AjQAKAAoAjgA9AD0AkAAdAB0AkgAQABAAkwAdAB0AlAAdAB0AsAAdAB0AswAdAB0AuQAdAB0AvgAQABAAvwAdAB0BWwAKAAoBfQAdAB0AAgABAAACJgAAAAEHAgAFAAACJwRYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgE0gRYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgE2gRYBNoF8AaOBFgEWARYBFgE2gXwBo4EWARYBFgEWATaBfAGjgRYBFgEWARYBNoF8AaOBFgEWARYBFgE2gTaBNoE2gTaBNoE2gTaBNoE2gTaBNoE2gTaBfAF8AaOBo4E2gTaBNoE2gXwBfAGjgaOBNoE2gRYBNoF8AaOBFgEWARYBFgE2gXwBFgEWAaOBFgEWATaBfAGjgRYBFgEWARYBNoF8AaOBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgEWARYBFgAFAByAAYABgBzABAAEAB0AAYABgB1AB0AHQB2AAYABgB7AB0AHQB8AB0AHQB9AB0AHQCIAB0AHQCJAB0AHQCMAB0AHQCOAD0APQCQAB0AHQCSABAAEACTAB0AHQCUAB0AHQCzAB0AHQC+ABAAEAC/AB0AHQF9AB0AHQABAI4ADQANAC4Ad//2//YAef/2//YAev/2//YAgv/2//YAg//s/+wAhP/h/+EAhf/h/+EAhv/h/+EAh//h/+EAjf/h/+EAj//h/+EAsv/2//YAt//s/+wAuP/s/+wBAf/2//YBAv/2//YBBv/2//YBC//2//YBDP/2//YBEP/2//YBEf/2//YBKf/2//YBKv/2//YBL//s/+wBMP/s/+wBMf/h/+EBMv/h/+EBM//h/+EBNP/h/+EBNf/h/+EBNv/h/+EBOf/h/+EBPP/h/+EBUv/s/+wBVf/s/+wBWP/s/+wBW//h/+EBXv/h/+EBYv/2//YBY//2//YBaP/2//YBbP/2//YBdv/6//oBe//2//YBfP/2//YBgv/6//oAGgB3//r/+gB5//r/+gB6//r/+gCD//3//QCE//b/9gCF//b/9gCG//b/9gCH//b/9gCP//b/9gCy//r/+gC3//b/9gC4//b/9gEv//3//QEw//3//QEx//b/9gEy//b/9gEz//b/9gE0//b/9gE1//b/9gE2//b/9gE5//b/9gE8//b/9gFS//b/9gFV//b/9gFY//b/9gFe//b/9gATAIT/zv/OAIX/zv/OAIb/zv/OAIf/zv/OAI//8//zALf/4P/gALj/4P/gATH/zv/OATL/zv/OATP/zv/OATT/zv/OATX/zv/OATb/zv/OATn/zv/OATz/zv/OAVL/4P/gAVX/4P/gAVj/4P/gAV7/8//zAAIAAQAAAiYAAAABDDgL/gACDE4ADAC/Av4DBAMKAxADFgMcAyIDKAMuAzQDOgNAA0YDTANSA1gDXgNkA2oDcAN2A3wDggOIA44DlAOaA6ADpgOsA7IDuAO+A8QDygPQA9YD3APiA+gD7gP0A/oEAAQGBAwEEgQYBB4EJAQqBDAENgQ8BEIESAROBFQEWgRgBGYEbARyBHgEfgSEBIoEkASWBJwEogSoBK4EtAS6BMAExgTMBNIE2ATeBOQE6gTwBPYE/AUCBQgFDgUUBRoFIAUmBSwFMgU4BT4FRAVKBVAFVgVcBWIFaAVuBXQFegWABYYFjAWSBZgFngWkBaoFsAW2BbwFwgXIBc4F1AXaBeAF5gXsBfIF+AX+BgQGCgYQBhYGHAYiBigGLgY0BjoGQAZGBkwGUgZYBl4GZAZqBnAGdgZ8BoIGiAaOBpQGmgagBqYGrAayBrgGvgbEBsoG0AbWBtwG4gboBu4G9Ab6BwAHBgcMBxIHGAceByQHKgcwBzYHPAdCB0gHTgdUB1oHYAdmB2wHcgd4B34HhAeKB5AHlgecB6IHqAeuB7QHugfAB8YHzAfSB9gH3gfkB+oH8Af2B/wIAggICA4IFAgaCCAIJggsCDIIOAg+CEQISghQCFYIXAhiCGgIbgh0CHoIgAiGCIwIkgiYCJ4IpAiqCLAItgi8CMIIyAjOCNQI2gjg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
`; 


// --- Initial Load ---
document.addEventListener('DOMContentLoaded', () => {
    if (window.isSecureContext === false && location.hostname !== "localhost" && location.hostname !== "127.0.0.1") {
        console.warn("Web Crypto API (crypto.subtle) is not available. Password hashing will be less secure. Ensure the page is served over HTTPS.");
        if(adminLoginMessageArea) displayMessage(adminLoginMessageArea, "هشدار: اتصال امن نیست. هشینگ رمز عبور با امنیت کمتر انجام می‌شود.", false);
    }

    if (checkAdminLogin()) {
        showAdminSectionContent('adminClassManagementSection'); 
        loadClassesForAdmin(); // This now populates both select dropdowns
        loadUsersForAdmin();
    } else {
        if (adminDashboardSection) adminDashboardSection.classList.add('hidden');
        if (adminLoginSection) adminLoginSection.classList.remove('hidden');
    }
});