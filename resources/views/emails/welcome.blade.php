@component('mail::message')
    # أهلاً {{ $user->name }}! 👋

    شكراً لانضمامك إلى **CaffeineCove** – نظام إدارة العيادات الذكي.

    يمكنك الآن:
    - إدارة المرضى والمواعيد
    - متابعة الفواتير والمدفوعات
    - إنشاء خطط العلاج
    - والكثير!

    قم بتسجيل الدخول من هنا:

    @component('mail::button', ['url' => config('app.frontend_url')])
        تسجيل الدخول
    @endcomponent

    إذا كان لديك أي أسئلة، لا تتردد في التواصل معنا.

    شكراً،
    فريق CaffeineCove
@endcomponent
