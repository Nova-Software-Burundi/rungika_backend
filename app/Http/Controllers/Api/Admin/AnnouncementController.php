<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $query = Announcement::with('user');

        if ($request->published !== null) {
            $query->where('published', filter_var($request->published, FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body'  => 'required|string',
        ]);

        $announcement = Announcement::create([
            'title'  => $data['title'],
            'body'   => $data['body'],
            'user_id' => auth()->id(),
        ]);

        return response()->json($announcement->load('user'), 201);
    }

    public function show(Announcement $announcement)
    {
        return $announcement->load('user');
    }

    public function update(Request $request, Announcement $announcement)
    {
        $data = $request->validate([
            'title'     => 'string|max:255',
            'body'      => 'string',
            'published' => 'boolean',
        ]);

        if (isset($data['published']) && $data['published'] && !$announcement->published_at) {
            $data['published_at'] = now();
        }

        $announcement->update($data);
        return $announcement->load('user');
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        return response()->noContent();
    }

    public function published()
    {
        return Announcement::published()->with('user')
            ->orderBy('published_at', 'desc')
            ->get();
    }

    public function togglePublish(Announcement $announcement)
    {
        $published = !$announcement->published;
        $announcement->update([
            'published'     => $published,
            'published_at'  => $published ? now() : null,
        ]);

        return $announcement->load('user');
    }
}
