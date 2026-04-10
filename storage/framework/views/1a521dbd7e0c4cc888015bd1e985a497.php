<table>
    <tr>
        <td colspan="9" style="text-align: center;background-color: #ffff99;">ข้อมูลลูกค้า</td>
    </tr>
    <tr>
        <td style="width: 50px; background-color: #d9ead3; text-align: center;">ลำดับ</td>
        <td style="width: 100px; background-color: #d9ead3; text-align: center;">รหัส</td>
        <td style="width: 300px; background-color: #d9ead3; text-align: center;">ชื่อ</td>
        <td style="width: 250px; background-color: #d9ead3; text-align: center;">นามสกุล</td>
        <td style="width: 200px; background-color: #d9ead3; text-align: center;">อีเมล</td>
        <td style="width: 200px; background-color: #d9ead3; text-align: center;">เบอร์โทร</td>
        <td style="width: 180px; background-color: #d9ead3; text-align: center;">เลขที่ผู้เสียภาษี</td>
        <td style="width: 150px; background-color: #d9ead3; text-align: center;">ประเภทสมาชิก</td>
        <th style="width: 100px; text-align: center;background-color: #d9ead3;">สำนักงานใหญ่หรือไม่</th>
    </tr>
    <?php
    $i = 1;
    ?>
    <?php $__currentLoopData = $data[0]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $customer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <tr>
        <td style="text-align: center;"><?php echo e($i++); ?></td>
        <td style="text-align: center;"><?php echo e($customer['no']); ?></td>
        <td style="text-align: left;"><?php echo e($customer['name']); ?></td>
        <td style="text-align: left;"><?php echo e($customer['lname']); ?></td>
        <td style="text-align: left;"><?php echo e($customer['email']); ?></td>
        <td style="text-align: right;">
            <?php if(!empty($customer['phone_number1'])): ?>
            <?php echo e($customer['phone_number1']); ?>

            <?php if(!empty($customer['phone_number2'])): ?>
            , <?php echo e($customer['phone_number2']); ?>

            <?php endif; ?>
            <?php elseif(!empty($customer['phone_number2'])): ?>
            <?php echo e($customer['phone_number2']); ?>

            <?php endif; ?>
        </td>
        <td style="text-align: right;"><?php echo e($customer['tax_id']); ?></td>
        <td style="text-align: center;"><?php echo e($customer['type']); ?></td>
        <td style="text-align: center;"><?php echo e($customer['is_headquarter']); ?></td>
    </tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</table>
<?php /**PATH /var/www/vhosts/dev-asha9.com/fie-api-uat.dev-asha9.com/resources/views/export/CustomerreportExport.blade.php ENDPATH**/ ?>