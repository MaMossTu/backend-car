<table >
    <tr>
        <th style="border: 1px solid black; text-align: center; width: 100px;">รหัสร้าน</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ชื่อลูกค้า</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">เลขที่ตรวจ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">วันที่</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">เลขทะเบียนวิศวกร</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">หมายเลขทะเบียนรถ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">จังหวัด</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">ยี่ห้อรถ</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">เลขใบติดตั้ง</th>
        <th style="border: 1px solid black; text-align: center; width: 100px;">เลขต่อถัง</th>
    </tr>
    <?php $No = 1; ?>
    <?php $__currentLoopData = $data; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $num => $dataloop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <tr>
        <td><?php echo e($dataloop->no ?? null); ?></td>
        <td><?php echo e($dataloop->name ?? null); ?></td>
        <td><?php echo e($dataloop->service_or ?? null); ?></td>
        <td><?php echo e($dataloop->day ?? null); ?></td>
        <td><?php echo e($dataloop->code ?? null); ?></td>
        <td><?php echo e($dataloop->license_plate ?? null); ?></td>
        <td><?php echo e($dataloop->province_name ?? null); ?></td>
        <td><?php echo e($dataloop->brand_name ?? null); ?></td>
        <td><?php echo e($dataloop->install_no ?? null); ?></td>
        <td><?php echo e($dataloop->CHA_no ?? null); ?></td>
    </tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>



</table>
<?php /**PATH /var/www/vhosts/dev-asha9.com/fie-api-uat.dev-asha9.com/resources/views/export/Gasreport.blade.php ENDPATH**/ ?>