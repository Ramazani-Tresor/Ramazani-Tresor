<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\Category;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::query()->withCount('products')
            ->when($request->q, fn ($q, $t) => $q->where('name', 'like', "%{$t}%"))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('categories.index', compact('categories'));
    }

    public function create()
    {
        return view('categories.form', ['category' => new Category(['is_active' => true, 'color' => '#1E6FD9'])]);
    }

    public function store(Request $request)
    {
        $category = Category::create($this->validated($request));
        AuditService::log('category.create', $category, "Catégorie « {$category->name} » créée");

        return redirect()->route('categories.index')->with('success', 'Catégorie créée.');
    }

    public function edit(Category $category)
    {
        return view('categories.form', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $category->update($this->validated($request, $category));
        AuditService::log('category.update', $category, "Catégorie « {$category->name} » modifiée");

        return redirect()->route('categories.index')->with('success', 'Catégorie modifiée.');
    }

    public function toggle(Category $category)
    {
        $category->update(['is_active' => ! $category->is_active]);
        AuditService::log('category.toggle', $category, ($category->is_active ? 'Activation' : 'Désactivation')." de « {$category->name} »");

        return back()->with('success', $category->is_active ? 'Catégorie réactivée.' : 'Catégorie désactivée.');
    }

    /** Suppression uniquement si aucun article n'y est rattaché (intégrité des données). */
    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            throw new BusinessException('Cette catégorie contient des articles : désactivez-la plutôt que de la supprimer.');
        }
        AuditService::log('category.delete', null, "Suppression de la catégorie « {$category->name} »");
        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Catégorie supprimée.');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('categories')->ignore($category)],
            'description' => 'nullable|string|max:255',
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
