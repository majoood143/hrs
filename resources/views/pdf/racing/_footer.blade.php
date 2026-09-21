<table width="100%" style="font-family: cairo; font-size: 7pt; color: #666666; border-top: 0.2mm solid #cccccc;">
    <tr>
        <td width="70%" style="text-align: {{ $rtl ? 'right' : 'left' }}; padding-top: 1mm;"><span dir="ltr">{{ $url }}</span> &nbsp;·&nbsp; {{ __('racing.pdf.generated', ['date' => now()->locale($locale)->translatedFormat('j F Y')]) }}</td>
        <td width="30%" style="text-align: {{ $rtl ? 'left' : 'right' }}; padding-top: 1mm;">{{ __('racing.pdf.page') }} {PAGENO} / {nbpg}</td>
    </tr>
</table>
