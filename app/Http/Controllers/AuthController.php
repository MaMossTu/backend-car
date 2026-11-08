<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuRole;
use App\Models\Permissions;
use App\Models\RolePermissions;
use App\Models\User;
use App\Models\UserPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Log;
use App\Models\Roles;
use Carbon\Carbon;

class AuthController extends Controller
{

    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register()
    {
        $validator = Validator::make(request()->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        $user = new User;
        $user->name = request()->name;
        $user->email = request()->email;
        $user->password = bcrypt(request()->password);
        $user->save();

        return response()->json($user, 201);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        $credentials = request(['email', 'password']);

        if (!$token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = auth()->user();
        $user->load(['Employees.Branches', 'UserPosition']);

        $userPosition = $user->UserPosition->first();
        $perid = $userPosition ? $userPosition->role_id : null;
        $items = $perid ? Roles::with('menu_permissions.menu')->find($perid) : null;

        return $this->respondWithToken($token, $user, $items);
    }
    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(auth()->user());
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */

    protected function respondWithToken($token, $user, $roleMenus)
    {
        return response()->json([
            'user' => $user,
            'role' => $roleMenus,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'code' => 200,
        ]);
    }

    public function getPage(Request $request)
    {
        $length = $request->length;
        $order = $request->order;
        $search = $request->search;
        $start = $request->start;
        $page = $start / $length + 1;

        $columns = [
            'id',
            'user_id',
            'description',
            'type',
            'created_at',
            'updated_at',
        ];

        $searchcolumn = ['description', 'type']; // Adjusted to more relevant fields

        $query = Log::select($columns)->with('user.Employees');

        // Ordering
        $orderBy = $columns[$order[0]['column']] ?? 'id';
        $query->orderBy($orderBy, $order[0]['dir']);

        // Search
        if (!empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchcolumn) {
                foreach ($searchcolumn as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });

            // Search in user.Employees
            $query->orWhereHas('user.Employees', function ($q) use ($search) {
                $q->where('first_name', 'like', '%' . $search['value'] . '%')
                    ->orWhere('last_name', 'like', '%' . $search['value'] . '%');
            });
        }

        // Filter by created_at between start_date and end_date
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay(); // 00:00:00
            $endDate = Carbon::parse($request->end_date)->endOfDay(); // 23:59:59

            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $items = $query->paginate($length, ['*'], 'page', $page);

        if ($items->isNotEmpty()) {
            $No = (($page - 1) * $length);
            foreach ($items as $item) {
                $No++;
                $item->No = $No;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }
}
