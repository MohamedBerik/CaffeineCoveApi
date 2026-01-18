<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $user = User::all();
        return view('User.index', ["result" => $user]);
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        return view("User.show", ["result" => $user]);
    }

    public function delete($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return redirect()->route("dashboard")->with("usermessage", "Deleted Successfully");
    }

    public function create()
    {
        return view("User.create");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|unique:users|max:20',
            'name' => 'required|min:3|max:255',
            'email' => 'required|min:3|max:255',
            'password' => 'required|min:3|max:255',
        ]);

        User::create([
            "id" => $request->id,
            "name" => $request->name,
            "email" => $request->email,
            "password" => Hash::make($request->password),
        ]);
        return redirect()->route("dashboard")->with("usermessage", "Created Successfully");
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        return view("User.edit", ["result" => $user]);
    }

    public function update(Request $request)
    {
        $old_id = $request->old_id;
        $user = User::findOrFail($old_id);

        $request->validate([
            "id" => ['required', Rule::unique('users')->ignore($old_id)],
            "name" => "required|min:3|max:255",
            "email" => "required|min:3|max:255",
            "password" => "required|min:3|max:255",
        ]);

        $user->update([
            "id" => $request->id,
            "name" => $request->name,
            "email" => $request->email,
            "password" => Hash::make($request->password),
        ]);
        return redirect()->route("dashboard")->with("usermessage", "Updated Successfully");
    }
}
