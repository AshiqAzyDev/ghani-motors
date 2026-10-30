<?php

namespace App\Http\Controllers;

use App\Models\CompliancePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompliancePolicyController extends Controller
{
    public function index(Request $request)
    {
        $staff = $request->user('staff');
        abort_unless($staff->can('policies.view'), 403);

        $policies = CompliancePolicy::query()
            ->where('dealer_id', $staff->dealer_id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('dealer.policies.index', [
            'policies' => $policies,
            'canManage' => $staff->can('policies.manage'),
        ]);
    }

    public function store(Request $request)
    {
        $staff = $request->user('staff');
        abort_unless($staff->can('policies.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $folder = 'compliance-policies/'.$staff->dealer_id;
        $path = $request->file('pdf')->store($folder, 'public');
        $maxSort = (int) CompliancePolicy::query()
            ->where('dealer_id', $staff->dealer_id)
            ->max('sort_order');

        CompliancePolicy::query()->create([
            'dealer_id' => $staff->dealer_id,
            'name' => $data['name'],
            'pdf_path' => $path,
            'original_filename' => $request->file('pdf')->getClientOriginalName(),
            'sort_order' => $maxSort + 1,
        ]);

        return redirect()
            ->route('dealer.policies')
            ->with('status', 'Compliance policy uploaded.');
    }

    public function destroy(Request $request, CompliancePolicy $policy)
    {
        $staff = $request->user('staff');
        abort_unless($staff->can('policies.manage'), 403);
        abort_unless((int) $policy->dealer_id === (int) $staff->dealer_id, 404);

        if ($policy->pdf_path) {
            Storage::disk('public')->delete($policy->pdf_path);
        }

        $policy->delete();

        return redirect()
            ->route('dealer.policies')
            ->with('status', 'Compliance policy removed.');
    }
}
