<?php

namespace Webkul\Admin\Http\Controllers\Marketing;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Marketing\Repositories\EventRepository;
use Webkul\Admin\DataGrids\Marketing\EventDataGrid;

class EventController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(protected EventRepository $eventRepository)
    {
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        if (request()->ajax()) {
            return app(EventDataGrid::class)->toJson();
        }

        return view('admin::marketing.email-marketing.events.index');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResource
     */
    public function store(): JsonResource
    {
        $this->validate(request(), [
            'name'        => 'required',
            'description' => 'required',
            'date'        => 'date|required',
        ]);

        Event::dispatch('marketing.events.create.before');

        $event = $this->eventRepository->create(request()->only([
            'name',
            'description',
            'date'
        ]));

        Event::dispatch('marketing.events.create.after', $event);

        return new JsonResource([
            'message' => trans('admin::app.marketing.email-marketing.events.index.create.success'),
        ]);
    }

    /**
     * Event Details
     *
     * @param  int  $id
     * @return JsonResource
     */
    public function edit($id): JsonResource
    {
        if ($id == 1) {
            session()->flash('error', trans('admin::app.marketing.events.edit-error'));

            return redirect()->back();
        }

        $event = $this->eventRepository->findOrFail($id);

        return new JsonResource([
            'data' => $event,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return JsonResource
     */
    public function update(): JsonResource
    {
        $id = request()->id;

        $this->validate(request(), [
            'name'        => 'required',
            'description' => 'required',
            'date'        => 'date|required',
        ]);

        Event::dispatch('marketing.events.update.before', $id);

        $event = $this->eventRepository->update(request()->only([
            'name',
            'description',
            'date'
        ]), $id);

        Event::dispatch('marketing.events.update.after', $event);

        return new JsonResource([
            'message' => trans('admin::app.marketing.email-marketing.events.index.edit.success'),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return JsonResource
     */
    public function destroy($id): JsonResource
    {
        $this->eventRepository->findOrFail($id);

        try {
            Event::dispatch('marketing.events.delete.before', $id);

            $this->eventRepository->delete($id);

            Event::dispatch('marketing.events.delete.after', $id);

            return new JsonResource([
                'message' => trans('admin::app.marketing.email-marketing.events.index.edit.delete-success'),
            ]);
        } catch (\Exception $e) {
        }

        return new JsonResource([
            'message' => trans('admin::app.response.delete-failed', ['name'  =>  'admin::app.marketing.email-marketing.events.index.event']),
        ], 500);
    }
}
