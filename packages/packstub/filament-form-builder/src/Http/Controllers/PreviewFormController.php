<?php

namespace Packstub\FormBuilder\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\Support\FormPreview;

/**
 * The editor's preview page: a parked draft drawn like the hosted page, in the language asked
 * for (?locale=, not ?lang=, which would also switch the admin's own session language).
 * Only for the admin who parked it; the iframe that loads it is sandboxed without forms,
 * so nothing can be submitted from it.
 */
class PreviewFormController
{
    public function __invoke(Request $request, string $token): View
    {
        abort_unless(Gate::allows('viewAny', FormBuilder::formModel()), 403);

        $state = FormPreview::find($token, $request->user()) ?? abort(404);

        $locale = (string) $request->query('locale', app()->getLocale());

        if (array_key_exists($locale, (array) config('languages.available', [app()->getLocale() => []]))) {
            app()->setLocale($locale);
        }

        $form = FormPreview::form($state);

        return view('packstub-form-builder::pages.show', [
            'form' => $form,
            'layout' => config('packstub-form-builder.routes.page_layout', 'packstub-form-builder::layout'),
        ]);
    }
}
