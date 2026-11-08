<table class="lh"><tr>
    <td class="lh-logo"><img src="{{ public_path('images/logo-print.png') }}" alt="" width="58"></td>
    <td class="lh-center"><div class="lh-c">{{ setting('country_header') }}</div><div class="lh-m">{{ setting('ministry_header') }}</div><div class="lh-u">{{ mb_strtoupper(setting('university_name')) }}@if(setting('university_acronym')) « {{ setting('university_acronym') }} »@endif</div><div class="lh-a">{{ setting('university_address') }} {{ setting('university_phone') ? '· '.setting('university_phone') : '' }} {{ setting('university_email') ? '· '.setting('university_email') : '' }}</div></td>
    <td class="lh-ref">Réf. : {{ $reference }}<br>Édité le {{ now()->format('d/m/Y à H:i') }}</td>
</tr></table>
