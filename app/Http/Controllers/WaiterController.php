<?php

namespace App\Http\Controllers;

use App\Models\Table;
use App\Models\Order;
use App\Models\Zone;
use Illuminate\Http\Request;

class WaiterController extends Controller
{
    /**
     * Главный экран - редирект на зал
     */
    public function index()
    {
        return redirect()->route('waiter.hall');
    }

    /**
     * Карта зала
     */
    public function hall()
    {
        $zones = Zone::with(['tables' => function ($q) {
            $q->orderBy('number');
        }])->where('restaurant_id', $this->getRestaurantId())->get();

        return view('waiter.hall', compact('zones'));
    }

    /**
     * Экран стола с заказами
     */
    public function table($id)
    {
        $table = Table::with(['orders' => function ($q) {
            $q->whereIn('status', ['new', 'open', 'cooking', 'ready'])
              ->with(['items.dish', 'customer']);
        }])->findOrFail($id);

        return view('waiter.table', compact('table'));
    }

    /**
     * Список заказов официанта
     */
    public function orders()
    {
        return view('waiter.orders');
    }

    /**
     * Профиль официанта
     */
    public function profile()
    {
        return view('waiter.profile');
    }

    /**
     * Получить ID ресторана
     */
    protected function getRestaurantId(): int
    {
        if (auth()->check() && auth()->user()->restaurant_id) {
            return auth()->user()->restaurant_id;
        }
        return 1;
    }
}
