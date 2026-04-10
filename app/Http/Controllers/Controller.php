<?php

namespace App\Http\Controllers;

use App\Models\Log;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Intervention\Image\Facades\Image;
use Maatwebsite\Excel\Facades\Excel;

use function PHPUnit\Framework\returnValueMap;

abstract class Controller
{
    public function returnSuccess($massage, $data)
    {

        return response()->json([
            'code' => strval(200),
            'status' => true,
            'message' => $massage,
            'data' => $data,
        ], 200);
    }

    public function returnUpdateReturnData($massage, $data)
    {
        return response()->json([
            'code' => strval(201),
            'status' => true,
            'message' => $massage,
            'data' => $data,
        ], 201);
    }

    public function returnErrorData($massage, $code)
    {
        return response()->json([
            'code' => strval($code),
            'status' => false,
            'message' => $massage,
            'data' => [],
        ], 404);
    }

    public function returnError($massage)
    {
        return response()->json([
            'code' => strval(401),
            'status' => false,
            'message' => $massage,
            'data' => [],
        ], 401);
    }

    public function returnBadRequest($error, $message = '')
    {
        return response()->json([
            'code' => strval(400),
            'message' => $message,
            'error' => $error,
        ], 400);
    }

    public function Log($userId, $description, $type)
    {
        $Log = new Log();
        $Log->user_id = $userId;
        $Log->description = $description;
        $Log->type = $type;
        $Log->save();
    }
    public function saveImage_branchs(Request $request, $name, $picname)
    {
        if ($request->hasFile($name)) {
            $file = $request->file($name);
            $filename = 'branchs_logo_' . $picname . '.' . $file->getClientOriginalExtension();
            $path = '/Branches/';
            Storage::disk('public')->putFileAs($path, $file, $filename);
            return 'storage' . $path . $filename;
        }
    }
    public function saveImageunder_branchs(Request $request, $name, $picname)
    {
        if ($request->hasFile($name)) {
            $file = $request->file($name);
            $filename = 'branchs_under_' . $picname . '.' . $file->getClientOriginalExtension();
            $path = '/Branches/';
            Storage::disk('public')->putFileAs($path, $file, $filename);
            return 'storage' . $path . $filename;
        }
    }

    public function save_Document($file, $picname, $no)
    {
        if ($file instanceof \Illuminate\Http\UploadedFile) {
            $fileName = $picname . '.' . $file->getClientOriginalExtension();
            $path = '/document/' . $no;
            if (!Storage::disk('public')->exists($path)) {
                Storage::disk('public')->makeDirectory($path);
            }
            $filePath = $file->storeAs($path, $fileName, 'public');

            return 'storage/' . $filePath;
        }

        throw new \Exception('Invalid file type.');
    }

    public function save_item($file, $picname,$name)
    {
        if ($file instanceof \Illuminate\Http\UploadedFile) {
            $fileName = $picname . '.' . $file->getClientOriginalExtension();
            $path = '/item/' . $name;
            if (!Storage::disk('public')->exists($path)) {
                Storage::disk('public')->makeDirectory($path);
            }
            $filePath = $file->storeAs($path, $fileName, 'public');

            return 'storage/' . $filePath;
        }

        throw new \Exception('Invalid file type.');
    }

    public function save_file($file, $picname,$name)
    {
        if ($file instanceof \Illuminate\Http\UploadedFile) {
            $fileName = $picname . '.' . $file->getClientOriginalExtension();
            $path = '/file/' . $name;
            if (!Storage::disk('public')->exists($path)) {
                Storage::disk('public')->makeDirectory($path);
            }
            $filePath = $file->storeAs($path, $fileName, 'public');

            return 'storage/' . $filePath;
        }

        throw new \Exception('Invalid file type.');
    }

    public function save_Insu_logo(Request $request, $name, $picname)
    {
        if ($request->hasFile($name)) {
            $file = $request->file($name);
            $filename = 'insurane_' . $picname . '.' . $file->getClientOriginalExtension();
            $path = '/Insu_logo/';
            Storage::disk('public')->putFileAs($path, $file, $filename);
            return 'storage' . $path . $filename;
        }
    }

    public function saveImage_gas(Request $request, $name, $picname)
    {
        if ($request->hasFile($name)) {
            $file = $request->file($name);
            $filename = 'gas_' . $picname . '.' . $file->getClientOriginalExtension();
            $path = '/vehicle_gas/';
            Storage::disk('public')->putFileAs($path, $file, $filename);
            return 'storage' . $path . $filename;
        }
    }

    public function saveImage_AddressEms(Request $request, $name, $picname)
    {
        if ($request->hasFile($name)) {
            $file = $request->file($name);
            $filename = 'AddressEms_' . $picname . '.' . $file->getClientOriginalExtension();
            $path = '/AddressEms/';
            Storage::disk('public')->putFileAs($path, $file, $filename);
            return 'storage' . $path . $filename;
        }
    }

    // public function save_Document(Request $request, $filename, $picname, $no)
    // {
    //     if ($request->hasFile($filename)) {
    //         $file = $request->file($filename);
    //         $fileName = $picname . '.' . $file->getClientOriginalExtension();
    //         $path = '/document/' . $no.'/';

    //         if (!Storage::disk('public')->exists($path)) {
    //             Storage::disk('public')->makeDirectory($path);
    //         }

    //         Storage::disk('public')->putFileAs($path, $file, $fileName);

    //         return 'storage' . $path . $fileName;
    //     }
    // }

    //ฟังชั่นการแปลงจํานวนเงินเป็นภาษาไทย
    public function convertToThaiText($number)
    {
        $txtnum1 = ['ศูนย์', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า', 'สิบ'];
        $txtnum2 = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน', 'ล้าน'];
        $number = str_replace([",", " ", "บาท"], "", $number);
        $number = explode(".", $number);
        if (sizeof($number) > 2) {
            return 'ข้อมูลไม่ถูกต้อง';
        }
        $strlen = strlen($number[0]);
        $convert = '';
        for ($i = 0; $i < $strlen; $i++) {
            $n = substr($number[0], $i, 1);
            if ($n != 0) {
                if ($i == ($strlen - 1) && $n == 1) {
                    $convert .= 'เอ็ด';
                } elseif ($i == ($strlen - 2) && $n == 2) {
                    $convert .= 'ยี่';
                } elseif ($i == ($strlen - 2) && $n == 1) {
                    $convert .= '';
                } else {
                    $convert .= $txtnum1[$n];
                }
                $convert .= $txtnum2[$strlen - $i - 1];
            }
        }

        $convert .= 'บาท';
        if (empty($number[1]) || $number[1] == '00') {
            $convert .= 'ถ้วน';
        } else {
            $strlen = strlen($number[1]);
            for ($i = 0; $i < $strlen; $i++) {
                $n = substr($number[1], $i, 1);
                if ($n != 0) {
                    if ($i == ($strlen - 1) && $n == 1) {
                        $convert .= 'เอ็ด';
                    } elseif ($i == ($strlen - 2) && $n == 2) {
                        $convert .= 'ยี่';
                    } elseif ($i == ($strlen - 2) && $n == 1) {
                        $convert .= '';
                    } else {
                        $convert .= $txtnum1[$n];
                    }
                    $convert .= $txtnum2[$strlen - $i - 1];
                }
            }
            $convert .= 'สตางค์';
        }
        return $convert;
    }

    function convertToThaiDate($date, $format = "j F Y") {
        // Ensure the input is a valid date
        if (!$timestamp = strtotime($date)) {
            return "Invalid date";
        }

        // Convert the year to the Thai year
        $thaiYear = date("Y", $timestamp) + 543;

        // Replace the year in the desired format
        $formattedDate = date($format, $timestamp);
        $formattedDate = str_replace(date("Y", $timestamp), $thaiYear, $formattedDate);

        // Convert month names to Thai
        $thaiMonths = [
            "January" => "มกราคม","February" => "กุมภาพันธ์",
            "March" => "มีนาคม","April" => "เมษายน",
            "May" => "พฤษภาคม","June" => "มิถุนายน",
            "July" => "กรกฎาคม","August" => "สิงหาคม",
            "September" => "กันยายน","October" => "ตุลาคม",
            "November" => "พฤศจิกายน","December" => "ธันวาคม"
        ];

        foreach ($thaiMonths as $en => $th) {
            $formattedDate = str_replace($en, $th, $formattedDate);
        }

        return $formattedDate;
    }

    function convertDateFormat($date, $format = "j F Y") {
        if (!$timestamp = strtotime($date)) {
            return "Invalid date";
        }

        // แปลงปีเป็น พ.ศ.
        $thaiYear = date("Y", $timestamp) + 543;

        // จัดรูปแบบวันที่เป็น d/m/Y
        $formattedDate = date("d/m", $timestamp) . '/' . $thaiYear;

        return $formattedDate;
    }
}
