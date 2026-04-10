<table>
    <tr style="background-color: #ffff99;">
        <td colspan="6" style="text-align: center;background-color: #ffff99;">รายการอะไหล่</td>
    </tr>
    <tr>
        <th style="width: 100px; text-align: center;background-color: #d9d9d9;">ลำดับ</th>
        <th style="width: 300px; text-align: center;background-color: #d9d9d9;">รายละเอียด</th>
        <th style="width: 150px; text-align: center;background-color: #d9d9d9;">จำนวน</th>
        <th style="width: 150px; text-align: center;background-color: #d9d9d9;">ราคา</th>
        <th style="width: 300px; text-align: center;background-color: #d9d9d9;">คำธิบาย</th>
        <th style="width: 300px; text-align: center;background-color: #d9d9d9;">ประเภท</th>
    </tr>
    <?php
    $i = 1;
    ?>
    <?php $__currentLoopData = $data[0]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sparepart): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <tr>
        <td style="text-align: center;"><?php echo e($i++); ?></td>
        <td style="text-align: left;"><?php echo e($sparepart['name']); ?></td>
        <td style="text-align: center;"><?php echo e($sparepart['qty']); ?></td>
        <td style="text-align: right;"><?php echo e($sparepart['price']); ?></td>
        <td style="text-align: right;"><?php echo e($sparepart['description']); ?></td>
        <td style="text-align: center;"><?php echo e($sparepart['ProductType']['name'] ?? ''); ?></td>
    </tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</table>
<?php /**PATH /var/www/vhosts/dev-asha9.com/fie-api-uat.dev-asha9.com/resources/views/export/SparepartreportExport.blade.php ENDPATH**/ ?>