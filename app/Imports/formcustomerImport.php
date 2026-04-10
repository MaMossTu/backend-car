<?php

namespace App\Imports;

use App\Models\Customers;
use App\Models\Provinces;
use App\Models\Districts;
use App\Models\Subdistricts;
use App\Models\CustomerAddresses;
use Exception;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class formcustomerImport implements ToModel, WithHeadingRow
{
    // ตัวแปรเก็บข้อมูลที่อ่านจากไฟล์
    public $importedData = [];

    public function model(array $row)
    {
        $this->importedData[] = $row;
        static $rowCount = 0;
        if ($rowCount++ <= 0) {
            return null; // ข้ามแถวที่ 1 และ 2
        }

        $type = $row['typepersonalvendoragent'];
        $is_headquarter = $row['is_headquarter01'] ?? null;

        $prefix = "";
        if ($type == "personal") {
            $prefix = "P-";
        } else if ($type == "vendor") {
            $prefix = "CS-";
        } else if ($type == "agent") {
            $prefix = "A-";
        }
        $no = IdGenerator::generate([
            'table' => 'customers',
            'field' => 'no',
            'length' => 8,
            'prefix' => $prefix,
            'reset_on_prefix_change' => true
        ]);


        $customer = Customers::create([
            'no' => $no,
            'name' => $row['name'],
            'email' => $row['email'],
            'phone_number1' => $row['phone_number1'],
            'phone_number2' => $row['phone_number2'],
            'tax_id' => $row['tax_id'],
            'type' => $row['typepersonalvendoragent'],
            'is_headquarter' => $is_headquarter,
        ]);

        $address = $row['note'] ?? '';
        if (empty($address)) {
            Log::error("Address is empty for customer: " . $row['name']);
            return null; // ข้ามข้อมูลนี้
        }
        $subdistrictName = $row['subdistrict'] ?? null; // แขวง/ตำบล
        $districtName = $row['district'] ?? null;    // เขต/อำเภอ
        $provinceName = $row['province'] ?? null;    // จังหวัด

        if (empty($subdistrictName) || empty($districtName) || empty($provinceName)) {
            Log::error("Address components are missing for customer: " . $row['name']);
            return null; // ข้ามข้อมูลนี้
        }

        // ค้นหา Province, District, Subdistrict ในฐานข้อมูล
        $province = Provinces::where('name_th_prefix', 'LIKE', "%$provinceName%")->first();
        if (!$province) {
            Log::error("Province not found: " . $provinceName);
            return null;
        }

        $district = Districts::where('name_th_prefix', 'LIKE', "%$districtName%")->where('province_id', $province->id)->first();
        if (!$district) {
            Log::error("District not found: " . $districtName . " in province " . $provinceName);
            return null;
        }

        $subdistrict = Subdistricts::where('name_th_prefix', 'LIKE', "%$subdistrictName%")->where('district_id', $district->id)->first();
        // dd($subdistrict);
        if (!$subdistrict) {
            Log::error("Subdistrict not found: " . $subdistrictName . " in district " . $districtName);
            return null;
        }

        $postalCode = optional($subdistrict)->zip_code ?? null;
        if (!$postalCode) {
            Log::error("Zip code not found for subdistrict: " . $subdistrictName);
            return null;
        }

        // 🔹 ตรวจสอบค่า customer_id ก่อนบันทึก
        if (!$customer || !$customer->id) {
            Log::error("Invalid customer data for address: " . $address);
            return null;
        }
        // $postalCode = $subdistrict->zip_code;
        // dd($postalCode);
        // dd(1);
        // 🔹 สร้างข้อมูลในฐานข้อมูล
        try {
            $customeraddress = CustomerAddresses::create([
                'address' => $address,
                'customer_id' => $customer->id,
                'subdistrict_id' => $subdistrict->id,
                'district_id' => $district->id,
                'province_id' => $province->id,
                'zip_code' => $postalCode,
            ]);
        } catch (\Exception $e) {
            Log::error("Error creating customer address: " . $e->getMessage());
            return null;
        }

        return $customeraddress;

        // if (!$address || !$customer->id || !$subdistrict->id || !$district->id || !$province->id || !$postalCode) {
        //     Log::error("Missing required data for CustomerAddresses: ", [
        //         'address' => $address,
        //         'customer_id' => $customer->id,
        //         'subdistrict_id' => $subdistrict->id,
        //         'district_id' => $district->id,
        //         'province_id' => $province->id,
        //         'zip_code' => $postalCode
        //     ]);
        //     return null; // ข้ามแถวนี้หากข้อมูลไม่สมบูรณ์

        //     try {
        //         $customeraddress = CustomerAddresses::create([
        //             'address' => $address,
        //             'customer_id' => $customer->id,
        //             'subdistrict_id' => $subdistrict->id,
        //             'district_id' => $district->id,
        //             'province_id' => $province->id,
        //             'zip_code' => $postalCode,
        //         ]);
        //     } catch (\Exception $e) {
        //         Log::error("Error creating customer address: " . $e->getMessage());
        //         return null;
        //     }
        // } else {
        //     Log::error("Address parsing failed for: " . $address);
        // }
    }

    // $address = $row['note'] ?? '';
    // if (empty($address)) {
    //     Log::error("Address is empty for customer: " . $row['name']);
    //     return null; // ข้ามข้อมูลนี้
    // }

    // if (preg_match($pattern, $address, $matches)) {
    //     dd(1);
    //     // ตรวจสอบค่า matches
    //     Log::info("Regex matches:", $matches);
    //     $subdistrictName = trim($matches[1] ?? ''); // แขวง/ตำบล
    //     $districtName = trim($matches[2] ?? '');    // เขต/อำเภอ
    //     $provinceName = trim($matches[3] ?? '');    // จังหวัด
    //     // $postalCode = trim($matches[4] ?? '');   // รหัสไปรษณีย์

    //     Log::info("Extracted Address Components:", [
    //         'subdistrict' => $subdistrictName,
    //         'district' => $districtName,
    //         'province' => $provinceName,
    //         // 'postalCode' => $postalCode
    //     ]);

    //     // dd($provinceName);

    //     // ค้นหา Province, District, Subdistrict ในฐานข้อมูล
    //     $province = Provinces::where('name_th', 'LIKE', "%$provinceName%")->first();


    //     $district = Districts::where('name_th', 'LIKE', "%$districtName%")->where('province_id', $province->id)->first();

    //     $subdistrict = Subdistricts::where('name_th', 'LIKE', "%$subdistrictName%")->where('district_id', $district->id)->first();
    //     // dd($subdistrict);

    //     $postalCode = $subdistrict->zip_code;

    //     if (!$address || !$customer->id || !$subdistrict->id || !$district->id || !$province->id || !$postalCode) {
    //         Log::error("Missing required data for CustomerAddresses: ", [
    //             'address' => $address,
    //             'customer_id' => $customer->id,
    //             'subdistrict_id' => $subdistrict->id,
    //             'district_id' => $district->id,
    //             'province_id' => $province->id,
    //             'zip_code' => $postalCode
    //         ]);
    //         return null; // ข้ามแถวนี้หากข้อมูลไม่สมบูรณ์
    //     }

    //     try {
    //         $customeraddress = CustomerAddresses::create([
    //             'address' => $address,
    //             'customer_id' => $customer->id,
    //             'subdistrict_id' => $subdistrict->id,
    //             'district_id' => $district->id,
    //             'province_id' => $province->id,
    //             'zip_code' => $postalCode,
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error("Error creating customer address: " . $e->getMessage());
    //         return null;
    //     }
    // } else {
    //     Log::error("Address parsing failed for: " . $address);
    // }


    // return $customer;
    // dd($customer);
    // }
}
        //
        // ดึงที่อยู่จากฟิลด์ 'note'
        // $address = $row['note'] ?? '';

        // // ใช้ Regular Expression เพื่อแยกข้อมูลที่อยู่
        // preg_match('/แขวง(.*?)เขต(.*?) (.*) (\d{5})/', $address, $matches);

        // $subdistrictName = trim($matches[1] ?? ''); // แขวง/ตำบล
        // $districtName = trim($matches[2] ?? '');    // เขต/อำเภอ
        // $provinceName = trim($matches[3] ?? '');    // จังหวัด
        // $postalCode = trim($matches[4] ?? '');      // รหัสไปรษณีย์

        // // ค้นหา Province, District, Subdistrict ในฐานข้อมูล
        // $province = Provinces::where('name_th', $provinceName)->first();
        // $district = Districts::where('name_th', $districtName)->where('province_id', $province->id ?? null)->first();
        // $subdistrict = Subdistricts::where('name_th', $subdistrictName)->where('district_id', $district->id ?? null)->first();

        // // บันทึกข้อมูลลูกค้า
        // return new Customers([
        //     'name' => $row['name'],
        //     'email' => $row['email'],
        //     'phone_number1' => $row['phone_number1'],
        //     'phone_number2' => $row['phone_number2'],
        //     'tax_id' => $row['tax_id'],
        //     'type' => $row['type'],
        //     'is_headquarter' => $row['is_headquarter'],

        // ]);


        // return DB::transaction(function () use ($row) {
        //     // ข้ามแถวที่ 1 และ 2
        //     static $rowCount = 0;
        //     if ($rowCount++ <= 2) {
        //         return null; // ข้ามแถวที่ 1 และ 2
        //     }
        //     // ดึงที่อยู่จากฟิลด์ 'note'
        //     $address = $row['note'] ?? '';

        //     // ใช้ Regular Expression แยกข้อมูลที่อยู่
        //     preg_match('/แขวง(.*?)เขต(.*?) (.*) (\d{5})/', $address, $matches);

        //     $subdistrictName = trim($matches[1] ?? ''); // แขวง/ตำบล
        //     $districtName = trim($matches[2] ?? '');    // เขต/อำเภอ
        //     $provinceName = trim($matches[3] ?? '');    // จังหวัด
        //     $postalCode = trim($matches[4] ?? '');      // รหัสไปรษณีย์

        //     // ค้นหา Province, District, Subdistrict ในฐานข้อมูล
        //     $province = Provinces::where('name_th', $provinceName)->first();
        //     $district = Districts::where('name_th', $districtName)->where('province_id', $province->id ?? null)->first();
        //     $subdistrict = Subdistricts::where('name_th', $subdistrictName)->where('district_id', $district->id ?? null)->first();


        //     $type = $row['type(personal,vendor,agent)'] ?? 'personal';
        //     $is_headquarter = $row['is_headquarter(0,1)'] ?? null;

        //     $prefix = "";
        //     if ($type == "personal") {
        //         $prefix = "P-";
        //     } else if ($type == "vendor") {
        //         $prefix = "CS-";
        //     } else if ($type == "agent") {
        //         $prefix = "A-";
        //     }
        //     $no = IdGenerator::generate([
        //         'table' => 'customers',
        //         'field' => 'no',
        //         'length' => 8,
        //         'prefix' => $prefix,
        //         'reset_on_prefix_change' => true
        //     ]);




            // return $no;
        //     $customerData = [
        //         'no' => $no,
        //         'name' => $row['name'],
        //         'email' => $row['email'],
        //         'phone_number1' => $row['phone_number1'],
        //         'phone_number2' => $row['phone_number2'],
        //         'tax_id' => $row['tax_id'],
        //         'type' => $type,
        //         'is_headquarter' =>  $is_headquarter,
        //     ];

        //     $addressData = [
        //         'address' => $row['note'],
        //         'subdistrict_id' => $subdistrict->id ?? null,
        //         'district_id' => $district->id ?? null,
        //         'province_id' => $province->id ?? null,
        //         'zip_code' => $postalCode
        //     ];

        //     // แสดงข้อมูลที่กำลังจะถูกบันทึก
        //     dd($customerData, $addressData);  // หรือใช้ dump($customerData, $addressData);

        //     // สร้างข้อมูลลูกค้าใหม่
        //     $customer = Customers::create($customerData);

        //     // สร้างข้อมูลที่อยู่ลูกค้าใหม่
        //     CustomerAddresses::create($addressData);

        //     // คืนค่าลูกค้าที่เพิ่มใหม่
        //     return $customer;
        // });

            // สร้างข้อมูลลูกค้าใหม่
        //     $customer = Customers::create([
        //         'no' => $no,
        //         'name' => $row['name'],
        //         'email' => $row['email'],
        //         'phone_number1' => $row['phone_number1'],
        //         'phone_number2' => $row['phone_number2'],
        //         'tax_id' => $row['tax_id'],
        //         'type' => $type,
        //         'is_headquarter' =>  $is_headquarter,
        //     ]);

        //     // สร้างข้อมูลที่อยู่ลูกค้าใหม่
        //     CustomerAddresses::create([
        //         'address' => $row['note'],
        //         'customer_id' => $customer->id,
        //         'subdistrict_id' => $subdistrict->id ?? null,
        //         'district_id' => $district->id ?? null,
        //         'province_id' => $province->id ?? null,
        //         'zip_code' => $postalCode
        //     ]);

        //     return $customer;
        // });

        // $customer = Customers::create([
        //     'name' => $row['name'],
        //     'email' => $row['email'],
        //     'phone_number1' => $row['phone_number1'],
        //     'phone_number2' => $row['phone_number2'],
        //     'tax_id' => $row['tax_id'],
        //     'type' => $row['type'],
        //     'is_headquarter' => $row['is_headquarter']
        // ]);
    // }
// }
