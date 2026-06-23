<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\UnderstandingCampaign;
use Illuminate\Http\Request;

class WelcomeFormController extends Controller
{
    public function show()
    {
        return view('welcome-form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'attended_on' => ['required', 'date'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'street_name' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:255'],
            're_dedicating' => ['required', 'boolean'],
            'first_time' => ['required', 'boolean'],
            'who_invited' => ['required', 'string', 'max:255'],
        ]);

        UnderstandingCampaign::create($data);

        // Use the explicit path, not route('welcome-form.show'): tenant routes are
        // registered late (TenancyServiceProvider's app->booted hook), so their
        // NAMES don't resolve via route() at request time — only the path matches.
        return redirect('/welcome')->with('success', true);
    }
}
