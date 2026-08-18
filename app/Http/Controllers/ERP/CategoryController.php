<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\StoreCategoryRequest;
use App\Http\Requests\ERP\UpdateCategoryRequest;
use App\Models\ERP\Category;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::withCount('products')
            ->with('parent:id,name')
            ->orderBy('name')
            ->paginate(20);

        return view('erp.categories.index', ['categories' => $categories]);
    }

    public function create(): View
    {
        return view('erp.categories.create', [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $category = Category::create($data + ['slug' => str()->slug($data['name']).'-'.uniqid()]);

        AuditLogger::log('created', $category, null, $category->toArray());

        return redirect()->route('categories.index')->with('success', 'تم إضافة التصنيف بنجاح');
    }

    public function edit(Category $category): View
    {
        return view('erp.categories.edit', [
            'category' => $category,
            'categories' => Category::whereKeyNot($category->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $data = $request->validated();

        $old = $category->only(array_keys($data));
        $category->update($data);

        AuditLogger::log('updated', $category, $old, $category->fresh()->only(array_keys($data)));

        return redirect()->route('categories.index')->with('success', 'تم تحديث التصنيف بنجاح');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'لا يمكن حذف تصنيف يحتوي على منتجات');
        }

        if ($category->children()->exists()) {
            $category->children()->update(['parent_id' => null]);
        }

        AuditLogger::log('deleted', $category, $category->toArray(), null);
        $category->delete();

        return redirect()->route('categories.index')->with('success', 'تم حذف التصنيف');
    }
}
