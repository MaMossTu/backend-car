<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UsersController extends Controller
{
    public function index()
    {
        $item = User::all();
        return $item;
    }

    public function showID($id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->returnBadRequest('User not found');
        }

        return $this->returnSuccess('Success', $user);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
            'password' => 'required|string|min:6',
            'employees_id' => 'required|integer|exists:employees,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบหรือไม่ถูกต้อง');
        }

        DB::beginTransaction();

        try {
            $payload = $validator->validated();

            $item = new User();
            $item->username = $payload['username'];
            $item->password = $payload['password'];
            $item->employees_id = $payload['employees_id'];
            $item->name = $payload['name'];
            $item->email = $payload['email'];
            $item->save();

            DB::commit();

            return $this->returnSuccess('Success', $item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('system error please try again ' . $e, 404);
        }
    }

    public function update(Request $request, $id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        $item = User::where('id', $id)->first();
        if (!$item) {
            return $this->returnBadRequest('User not found');
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|min:6',
            'employees_id' => 'required|integer|exists:employees,id',
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
        ]);

        if ($validator->fails()) {
            return $this->returnBadRequest($validator->errors(), 'ข้อมูลไม่ครบหรือไม่ถูกต้อง');
        }

        DB::beginTransaction();

        try {
            $payload = $validator->validated();

            $item->username = $payload['username'];
            if (!empty($payload['password'])) {
                $item->password = $payload['password'];
            }
            $item->employees_id = $payload['employees_id'];
            $item->name = $payload['name'];
            $item->email = $payload['email'];
            $item->save();

            DB::commit();

            $userId = $loginBy->id;
            $type = 'แก้ไขข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $item->id;
            $this->Log($userId, $description, $type);

            return $this->returnSuccess('Success', $item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('system error please try again ' . $e, 404);
        }
    }

    public function destroy($id)
    {
        $loginBy = Auth()->user();
        if (!isset($loginBy)) {
            return $this->returnError('ไม่พบข้อมูลผู้ใช้งาน กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        }

        DB::beginTransaction();

        try {
            $currentTimestamp = Carbon::now()->format('YmdHis');
            $item = User::find($id);
            if (!$item) {
                return $this->returnBadRequest('User not found');
            }

            // Keep unique email available before soft delete.
            $item->email = Str::random(10) . $currentTimestamp;
            $item->save();
            $item->delete();

            DB::commit();

            $userId = $loginBy->id;
            $type = 'ลบข้อมูล';
            $description = 'ผู้ใช้งาน ' . $loginBy->name . ' ได้ทำการ ' . $type . ' id ' . $item->id;
            $this->Log($userId, $description, $type);

            return $this->returnUpdateReturnData('delete success', $item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->returnErrorData('system error ' . $e, 404);
        }
    }

    public function getPage(Request $request)
    {
        $length = (int) $request->input('length', 10);
        $length = $length > 0 ? $length : 10;
        $order = $request->input('order', [['column' => 0, 'dir' => 'desc']]);
        $search = $request->input('search', ['value' => '']);
        $start = (int) $request->input('start', 0);
        $page = $start / $length + 1;

        $columns = [
            'id',
            'name',
            'email',
            'email_verified_at',
            'username',
            'employees_id',
        ];
        $searchcolumn = ['name', 'email', 'username'];

        $query = User::select($columns)->with('UserPosition.Roles', 'Employees');

        $orderBy = $columns[$order[0]['column'] ?? 0] ?? 'id';
        $orderDir = strtolower($order[0]['dir'] ?? 'desc');
        $orderDir = in_array($orderDir, ['asc', 'desc'], true) ? $orderDir : 'desc';
        $query->orderBy($orderBy, $orderDir);

        if (!empty($search['value'])) {
            $query->where(function ($query) use ($search, $searchcolumn) {
                foreach ($searchcolumn as $column) {
                    $query->orWhere($column, 'like', '%' . $search['value'] . '%');
                }
            });
        }

        $items = $query->paginate($length, ['*'], 'page', $page);

        if ($items->isNotEmpty()) {
            $no = (($page - 1) * $length);
            foreach ($items as $item) {
                $no++;
                $item->No = $no;
            }
        }

        return $this->returnSuccess('เรียกดูข้อมูลสำเร็จ', $items);
    }
}
