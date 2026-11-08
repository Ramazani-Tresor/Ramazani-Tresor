<?php

namespace App\Livewire;

use App\Models\Employee;
use App\Models\Grade;
use App\Models\OrgUnit;
use App\Models\StaffCategory;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Fichier du personnel interactif. Le périmètre de l'utilisateur (unité, famille) est appliqué côté serveur à chaque
 * requête ; avec la seule permission « annuaire », seules les données minimales sont affichées.
 */
class EmployeeDirectory extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $family = '';

    #[Url(except: '')]
    public string $grade = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $unit = '';

    #[Url(except: 'current')]
    public string $status = 'current';

    public function updating($name): void
    {
        if ($name !== 'page') {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'family', 'grade', 'category', 'unit']);
        $this->status = 'current';
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();
        $full = $user->hasPermission('employees.view');
        $q = Employee::query()->with(['grade', 'category', 'activeAssignment.orgUnit'])->search($this->search);
        $user->scopeEmployees($q, $full ? 'employees.view' : 'employees.directory');
        if ($this->family !== '') {
            $q->where('staff_family', $this->family);
        }
        if ($this->grade !== '') {
            $q->where('grade_id', (int) $this->grade);
        }
        if ($this->category !== '') {
            $q->where('staff_category_id', (int) $this->category);
        }
        if ($this->unit !== '') {
            $q->inUnit((int) $this->unit);
        }
        if ($this->status === 'current') {
            $q->whereNotIn('status', Employee::ENDED_STATUSES);
        } elseif ($this->status !== 'all') {
            $q->where('status', $this->status);
        }

        return view('livewire.employee-directory', [
            'employees' => $q->orderBy('last_name')->orderBy('first_name')->paginate(25),
            'full' => $full,
            'grades' => Grade::query()->where('is_active', true)->orderBy('family')->orderByDesc('rank')->get(),
            'categories' => StaffCategory::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'units' => OrgUnit::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
