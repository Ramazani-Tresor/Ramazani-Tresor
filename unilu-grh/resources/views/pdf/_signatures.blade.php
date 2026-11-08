<table class="sign"><tr>
    <td>{{ setting('signatory_1_title') }}<br><br><br><br>{{ setting('signatory_1_name') ?: '……………………………………' }}</td>
    <td>Fait à {{ explode(',', setting('university_address') ?: 'Likasi')[0] }}, le {{ now()->format('d/m/Y') }}<br>{{ setting('signatory_2_title') }}<br><br><br>{{ setting('signatory_2_name') ?: '……………………………………' }}</td>
</tr></table>
