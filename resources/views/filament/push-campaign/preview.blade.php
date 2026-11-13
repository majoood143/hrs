{{-- The campaign as a phone shows it, in both languages (admin → Mobile app → Notifications form). --}}
@php
    $card = 'border-radius:18px;padding:12px 14px;background:rgba(127,127,127,.12);border:1px solid rgba(127,127,127,.2);display:flex;gap:10px;align-items:flex-start;max-width:380px;width:100%';
@endphp
<div style="display:flex;flex-wrap:wrap;gap:16px">
    @foreach ($messages as $lang => $message)
        <div style="flex:1 1 300px;min-width:0">
            <div style="font-size:12px;opacity:.6;margin-bottom:6px">{{ $lang === 'ar' ? __('admin_mobile_app.campaigns.fields.preview_ar') : __('admin_mobile_app.campaigns.fields.preview_en') }}</div>
            <div dir="{{ $lang === 'ar' ? 'rtl' : 'ltr' }}" style="{{ $card }}">
                <x-filament::icon icon="heroicon-s-bell" style="width:28px;height:28px;flex:none;padding:5px;border-radius:7px;background:var(--primary-600);color:#fff" />
                <div style="min-width:0;flex:1">
                    <div style="display:flex;justify-content:space-between;gap:8px;font-size:12px;opacity:.65">
                        <span>{{ $app }}</span>
                        <span>{{ $lang === 'ar' ? 'الآن' : 'now' }}@if ($silent) · 🔕@endif</span>
                    </div>
                    <div style="font-weight:600;font-size:14px;overflow-wrap:anywhere">{{ filled($message['title']) ? $message['title'] : '…' }}</div>
                    @if (filled($message['body']))
                        <div style="font-size:13px;opacity:.85;overflow-wrap:anywhere;display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden">{{ $message['body'] }}</div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>
