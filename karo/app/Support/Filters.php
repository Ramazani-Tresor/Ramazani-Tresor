<?php

namespace App\Support;

use App\Models\AcademicYear;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\Faculty;
use App\Models\FeeCategory;
use App\Models\Program;
use App\Models\Promotion;
use Illuminate\Http\Request;

/** Filtres communs (année, faculté, filière, promotion, frais, banque, devise, période), restreints au périmètre. */
final class Filters
{
    public ?AcademicYear $year;

    public ?array $facultyScope;

    public array $values = [];

    public static function from(Request $request, string $permission): self
    {
        $f = new self;
        $user = $request->user();
        $f->facultyScope = $user->facultyScope($permission);
        $years = $user->yearScope($permission);
        $f->year = $request->filled('year') ? AcademicYear::query()->find($request->integer('year')) : AcademicYear::current();
        if ($years !== null && $f->year && ! in_array($f->year->id, $years, true)) {
            $f->year = AcademicYear::query()->whereIn('id', $years)->orderByDesc('starts_on')->first();
        }
        $faculty = $request->integer('faculty') ?: null;
        if ($faculty && $f->facultyScope !== null && ! in_array($faculty, $f->facultyScope, true)) {
            abort(403);
        }
        $f->values = [
            'year' => $f->year?->id, 'faculty' => $faculty, 'program' => $request->integer('program') ?: null,
            'promotion' => $request->integer('promotion') ?: null, 'fee' => $request->integer('fee') ?: null,
            'account' => $request->integer('account') ?: null, 'currency' => $request->filled('currency') ? strtoupper((string) $request->input('currency')) : null,
            'from' => $request->date('from')?->toDateString(), 'to' => $request->date('to')?->toDateString(),
            'status' => $request->input('status'), 'q' => $request->input('q'),
        ];

        return $f;
    }

    public function toMetrics(): array
    {
        return [
            'year_id' => $this->values['year'], 'faculty_ids' => $this->facultyScope, 'faculty_id' => $this->values['faculty'],
            'program_id' => $this->values['program'], 'promotion_id' => $this->values['promotion'], 'fee_category_id' => $this->values['fee'],
            'bank_account_id' => $this->values['account'], 'currency' => $this->values['currency'],
            'from' => $this->values['from'], 'to' => $this->values['to'] ?? now()->toDateString(),
        ];
    }

    public function get(string $k): mixed
    {
        return $this->values[$k] ?? null;
    }

    public function options(): array
    {
        $faculties = Faculty::query()->where('is_active', true)->when($this->facultyScope !== null, fn ($q) => $q->whereIn('id', $this->facultyScope ?: [0]))->orderBy('name')->get();

        return [
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'faculties' => $faculties,
            'programs' => Program::query()->with('department')->whereHas('department', fn ($d) => $d->whereIn('faculty_id', $faculties->pluck('id')))->orderBy('name')->get(),
            'promotions' => Promotion::query()->with('program.department')->whereHas('program.department', fn ($d) => $d->whereIn('faculty_id', $faculties->pluck('id')))->orderBy('code')->get(),
            'fees' => FeeCategory::query()->orderBy('priority')->get(),
            'accounts' => BankAccount::query()->with('bank')->get(),
            'currencies' => Currency::query()->where('is_active', true)->pluck('code'),
        ];
    }
}
