<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HorseGender;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;

class HorseGenderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $horse_gender = HorseGender::paginate(15);
        return view('admin.horse.horse-gender.index', compact('horse_gender'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.horse.horse-gender.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['en_name' => ['required', 'max:80', 'unique:horse_genders,en_name']]);
        $request->validate(['ar_name' => ['required', 'max:80']]);

        $type = new HorseGender();
        $type->en_name = $request->en_name;
        $type->ar_name = $request->ar_name;

        $type->save();
        flash()->success('The Horse Gender has been Added');
        return to_route('admin.horse-gender.index');
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
    public function edit(HorseGender $horse_gender)
    {
        return view('admin.horse.horse-gender.update', compact('horse_gender'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, HorseGender $horse_gender)
    {
        $request->validate(['en_name' => ['required', 'max:80', 'unique:horse_genders,en_name']]);
        $request->validate(['ar_name' => ['required', 'max:80']]);


        $horse_gender->en_name = $request->en_name;
        $horse_gender->ar_name = $request->ar_name;

        $horse_gender->save();
        flash()->success('The Horse Gender has been updated');


        return to_route('admin.horse-gender.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(HorseGender $horse_gender)
    {
        try {
            $horse_gender->delete();
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
