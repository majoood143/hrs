<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HorseStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;

class HorseStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $horse_status = HorseStatus::paginate(15);
        return view('admin.horse.horse-status.index', compact('horse_status'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.horse.horse-status.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['en_name' => ['required', 'max:80', 'unique:horse_statuses,en_name']]);
        $request->validate(['ar_name' => ['required', 'max:80']]);

        $type = new HorseStatus();
        $type->en_name = $request->en_name;
        $type->ar_name = $request->ar_name;

        $type->save();
        flash()->success('The Horse Status has been Added');
        return to_route('admin.horse-status.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(HorseStatus $horse_status)
    {
        return view('admin.horse.horse-status.update', compact('horse_status'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, HorseStatus $horse_status)
    {
        $request->validate(['en_name' => ['required', 'max:80', 'unique:horse_statuses,en_name']]);
        $request->validate(['ar_name' => ['required', 'max:80']]);


        $horse_status->en_name = $request->en_name;
        $horse_status->ar_name = $request->ar_name;

        $horse_status->save();
        flash()->success('The Horse Status has been updated');


        return to_route('admin.horse-status.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(HorseStatus $horse_status)
    {
        try {
            $horse_status->delete();
            flash()->success("Your changes have been successfully saved!");
            return response(['message' => 'Deleted'], 200);
        } catch (Exception $e) {
            logger($e);
             // Display an error notification
             //flash()->error("You must fill out the form before moving forward");
            return response(['message' => 'Someting went wrong!'], 500);
        }
    }
}
