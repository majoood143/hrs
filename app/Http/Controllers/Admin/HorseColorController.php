<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HorseColor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;

class HorseColorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $horse_color = HorseColor::paginate(10);
        return view('admin.horse.horse-color.index', compact('horse_color'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.horse.horse-color.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['en_name' => ['required', 'max:80', 'unique:horse_colors,en_name']]);
        $request->validate(['ar_name' => ['required', 'max:80']]);
        $request->validate(['pattern' => ['required', 'max:80']]);

        $horse_color = new HorseColor();
        $horse_color->en_name = $request->en_name;
        $horse_color->ar_name = $request->ar_name;
        $horse_color->pattern = $request->pattern; 

        $horse_color->save();
        flash()->success('The Horse Color has been Added');
        return to_route('admin.horse-color.index');
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
    public function edit(HorseColor $horse_color)
    {
        return view('admin.horse.horse-color.update', compact('horse_color'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, HorseColor $horse_color)
    {
        $request->validate(['en_name' => ['required', 'max:80', 'unique:horse_colors,en_name']]);
        $request->validate(['ar_name' => ['required', 'max:80']]);
        $request->validate(['pattern' => ['required', 'max:80']]);


        $horse_color->en_name = $request->en_name;
        $horse_color->ar_name = $request->ar_name;
        $horse_color->pattern = $request->pattern;

        $horse_color->save();
        flash()->success('The Horse Color has been updated');


        return to_route('admin.horse-color.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(HorseColor $horse_color)
    {
        try {
            $horse_color->delete();
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
