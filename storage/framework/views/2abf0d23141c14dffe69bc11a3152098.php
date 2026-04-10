<table >
    <tr>
        <th colspan="11" style="border: 1px solid black;border:1px solid #333; font-size: 18px;" align="center">
            รายการตรวจสภาพรถ <?php echo e(\Carbon\Carbon::parse($startDate)->addYears(543)->format('d/m/Y')); ?> ถึง <?php echo e(\Carbon\Carbon::parse($endDate)->addYears(543)->format('d/m/Y')); ?>

        </th>
    </tr>
    <tr>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ลำดับ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">เลขที่</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ทะเบียน</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">จังหวัด</th>
        <th style="border: 1px solid black; text-align: center; width: 200px;">ประเภท</th>
        <th style="border: 1px solid black; text-align: center; width: 300px;">ชื่อลูกค้า</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">วันที่จดทะเบียน</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">วันที่ตรวจ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ผลตรวจ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ค่าตรวจ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">รายละเอียด</th>
    </tr>
    <?php 
    $No = 1; 
    
    ?>
    <?php $__currentLoopData = $data; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $num => $dataloop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php
        $registrationDate = !empty($dataloop['registration_date']) 
            ? $dataloop['registration_date'] 
            : '-';
        $inspectionDate = !empty($dataloop['date']) 
            ? $dataloop['date'] 
            : '-';
    ?>
    <tr>
        <td style="border: 1px solid black;text-align: center;"><?php echo e($No++); ?></td>
        <td style="border: 1px solid black;text-align: center;"><?php echo e($dataloop['no'] ?? "-"); ?></td>
        <td style="border: 1px solid black;text-align: center;"><?php echo e($dataloop['license_plate'] ?? "-"); ?></td>
        <td style="border: 1px solid black;text-align: center;"><?php echo e($dataloop['provinces'] ?? "-"); ?></td>
        <td style="border: 1px solid black;"><?php echo e($dataloop['vehicle_inspection_types'] ?? "-"); ?></td>
        <td style="border: 1px solid black;"><?php echo e($dataloop['name'] ?? "-"); ?></td>
        <td style="border: 1px solid black;text-align: center;"><?php echo e($registrationDate); ?></td>
        <td style="border: 1px solid black;text-align: center;"><?php echo e($inspectionDate); ?></td>
        <td style="border: 1px solid black;text-align: center;"><?php echo e($dataloop['is_pass'] ?? "-"); ?></td>
        <td style="border: 1px solid black;text-align: center;"><?php echo e($dataloop['total_price'] ?? "-"); ?></td>
        <td style="border: 1px solid black;"><?php echo e($dataloop['result'] ?? "-"); ?></td>
    </tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</table>
<?php /**PATH /var/www/vhosts/dev-asha9.com/fie-api-uat.dev-asha9.com/resources/views/export/CarCheckReport.blade.php ENDPATH**/ ?>