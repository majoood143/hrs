<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HorsePollination;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;

class HorsePollinationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $horse_pollination = HorsePollination::paginate(10);
        return view('admin.horse.horse-pollination.index', compact('horse_pollination'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.horse.horse-pollination.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['en_name' => ['required', 'max:80', 'unique:horse_pollinations,en_name']]);
        $request->validate(['ar_name' => ['required', 'max:80']]);

        $type = new HorsePollination();
        $type->en_name = $request->en_name;
        $type->ar_name = $request->ar_name;

        $type->save();
        flash()->success('The Horse Pollination has been Added');
        return to_route('admin.horse-pollination.index');
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
    public function edit(HorsePollination $horse_pollination)
    {
        return view('admin.horse.horse-pollination.update', compact('horse_pollination'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, HorsePollination $horse_pollination)
    {
        $request->validate(['en_name' => ['required', 'max:80', 'unique:horse_pollinations,en_name']]);
        $request->validate(['ar_name' => ['required', 'max:80']]);


        $horse_pollination->en_name = $request->en_name;
        $horse_pollination->ar_name = $request->ar_name;

        $horse_pollination->save();
        flash()->success('The Horse Pollination has been updated');


        return to_route('admin.horse-pollination.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(HorsePollination $horse_pollination)
    {
        try {
            $horse_pollination->delete();
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
