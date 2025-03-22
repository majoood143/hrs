<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HorseType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;

class HorseTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $horse_Types = HorseType::paginate(15);
        return view('admin.horse.horse-type.index', compact('horse_Types'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.horse.horse-type.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['en_name' => ['required', 'max:80', 'unique:horse_types,en_name']]);
        $request->validate(['ar_name' => ['required', 'max:80']]);

        $type = new HorseType();
        $type->en_name = $request->en_name;
        $type->ar_name = $request->ar_name;

        $type->save();

        return to_route('admin.horse-type.index');
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
    public function edit(HorseType $horse_type)
    {
        return view('admin.horse.horse-type.update', compact('horse_type'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, HorseType $horse_type)
    {
        $request->validate(['en_name' => ['required', 'max:80', 'unique:horse_types,en_name']]);
        $request->validate(['ar_name' => ['required', 'max:80']]);


        $horse_type->en_name = $request->en_name;
        $horse_type->ar_name = $request->ar_name;

        $horse_type->save();
        flash()->success('The Horse type has been updated');


        return to_route('admin.horse-type.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(HorseType $horse_type)
    {
        try {
            $horse_type->delete();
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
