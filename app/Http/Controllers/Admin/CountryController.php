<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;

class CountryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $country = Country::paginate(10);
        return view('admin.horse.country.index', compact('country'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.horse.country.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['en_name' => ['required', 'max:80', 'unique:countries,en_name']]);
        $request->validate(['ar_name' => ['required', 'max:80']]);


        $country = new Country();
        $country->en_name = $request->en_name;
        $country->ar_name = $request->ar_name;


        $country->save();
        flash()->success('The Country has been Added');
        return to_route('admin.country.index');
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
    public function edit(Country $country)
    {
        return view('admin.horse.country.update', compact('country'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Country $country)
    {
        $request->validate(['en_name' => ['required', 'max:80', 'unique:countries,en_name']]);
        $request->validate(['ar_name' => ['required', 'max:80']]);


        $country->en_name = $request->en_name;
        $country->ar_name = $request->ar_name;


        $country->save();
        flash()->success('The Country has been updated');


        return to_route('admin.country.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Country $country)
    {
        try {
            $country->delete();
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
