@component('emails.partials.email-layout', ['branding' => $branding, 'title' => $title])

{{-- AI-generated campaign body (fr + en, already assembled by the sender) --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 32px 40px; text-align: left; font-size: 15px; line-height: 1.6; color: #4a4a4a;">
            {!! $html !!}
        </td>
    </tr>
</table>

{{-- Unsubscribe --}}
<table style="border-collapse: collapse; border-spacing: 0; width: 100%;" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="padding: 0 40px 32px; text-align: center;">
            <p style="margin: 0; font-size: 12px; color: #b9b9bb;">
                <a href="{{ $unsubscribeUrl }}" style="color: #b9b9bb; text-decoration: underline;">Se désinscrire / Unsubscribe</a>
            </p>
        </td>
    </tr>
</table>

@endcomponent
