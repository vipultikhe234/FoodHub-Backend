<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\State;
use App\Models\City;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    // ──── PUBLIC READS (for cascading dropdowns) ────────────────────────────

    public function countries()
    {
        return response()->json(Country::where('is_active', true)->orderBy('name')->get());
    }

    public function states(Request $request)
    {
        $request->validate(['country_id' => 'required|exists:countries,id']);
        return response()->json(
            State::where('country_id', $request->country_id)
                 ->where('is_active', true)
                 ->orderBy('name')
                 ->get()
        );
    }

    public function cities(Request $request)
    {
        $query = City::where('is_active', true)->orderBy('name');
        
        if ($request->has('state_id')) {
            $query->where('state_id', $request->state_id);
        }

        return response()->json($query->get());
    }

    // ──── ADMIN CRUD ────────────────────────────────────────────────────────

    // Countries
    public function allCountries()
    {
        return response()->json(Country::withCount('states')->orderBy('name')->get());
    }

    public function storeCountry(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'code'      => 'nullable|string|max:10',
            'is_active' => 'boolean',
        ]);
        return response()->json(Country::create($data), 201);
    }

    public function updateCountry(Request $request, $id)
    {
        $country = Country::findOrFail($id);
        $data = $request->validate([
            'name'      => 'sometimes|string|max:100',
            'code'      => 'nullable|string|max:10',
            'is_active' => 'boolean',
        ]);
        $country->update($data);
        return response()->json($country);
    }

    public function destroyCountry($id)
    {
        Country::findOrFail($id)->delete();
        return response()->json(['message' => 'Country deleted.']);
    }

    // States
    public function allStates(Request $request)
    {
        $query = State::with('country')->orderBy('name');
        if ($request->country_id) $query->where('country_id', $request->country_id);
        return response()->json($query->get());
    }

    public function storeState(Request $request)
    {
        $data = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'name'       => 'required|string|max:100',
            'is_active'  => 'boolean',
        ]);
        return response()->json(State::create($data)->load('country'), 201);
    }

    public function updateState(Request $request, $id)
    {
        $state = State::findOrFail($id);
        $data = $request->validate([
            'country_id' => 'sometimes|exists:countries,id',
            'name'       => 'sometimes|string|max:100',
            'is_active'  => 'boolean',
        ]);
        $state->update($data);
        return response()->json($state->load('country'));
    }

    public function destroyState($id)
    {
        State::findOrFail($id)->delete();
        return response()->json(['message' => 'State deleted.']);
    }

    // Cities
    public function allCities(Request $request)
    {
        $query = City::with(['state.country'])->orderBy('name');
        if ($request->state_id) $query->where('state_id', $request->state_id);
        return response()->json($query->get());
    }

    public function storeCity(Request $request)
    {
        $data = $request->validate([
            'state_id'  => 'required|exists:states,id',
            'name'      => 'required|string|max:100',
            'is_active' => 'boolean',
        ]);
        return response()->json(City::create($data)->load('state.country'), 201);
    }

    public function updateCity(Request $request, $id)
    {
        $city = City::findOrFail($id);
        $data = $request->validate([
            'state_id'  => 'sometimes|exists:states,id',
            'name'      => 'sometimes|string|max:100',
            'is_active' => 'boolean',
        ]);
        $city->update($data);
        return response()->json($city->load('state.country'));
    }

    public function destroyCity($id)
    {
        City::findOrFail($id)->delete();
        return response()->json(['message' => 'City deleted.']);
    }
}
