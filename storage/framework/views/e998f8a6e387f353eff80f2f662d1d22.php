<?php if($tax_vehicle == 'check' || $tax_vehicle == 'wait'): ?>
    <table >
        <tr>
            <td colspan="9" style="text-align: center; font-size: 14px; font-weight: bold;">รายงานภาษีรถยนต์ วันที่ <?php echo e($start_date); ?> - <?php echo e($end_date); ?></td>
        </tr>
    </table>
<?php endif; ?>
<?php if($tax_vehicle == 'send'): ?>
    <table >
        <tr>
            <td colspan="9" style="text-align: center; font-size: 14px; font-weight: bold;">รายงานนำส่งเพื่อชำระภาษี วันที่ <?php echo e($start_date); ?> - <?php echo e($end_date); ?></td>
        </tr>
        <tr>
            <th style="border: 1px solid black; text-align: center; width: 100px;">ลำดับ</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เลขที่</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">ทะเบียน</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">วันรับงาน</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เอกสาร</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">นัดรับป้าย</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">ภาษี</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เงินเพิ่ม</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">รวม</th>
        </tr>
        <?php
            $No = 1;
            $total_tax = 0;
        ?>
        <?php $__currentLoopData = $data; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $num => $dataloop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $type_document = '';
                if($dataloop['inspection_vehicles'][0]['type_document'] == 'copy'){
                    $type_document = 'สำเนา';
                }else {
                    $type_document = 'เล่ม';
                }

                $tax = 0;
                $tax_up = 0;
                foreach ($dataloop['inspection_vehicles'] as $inspection_vehicles) {
                    foreach ($inspection_vehicles['vehicle_service_transaction'] as $taxloop) {
                        if($taxloop['service_id'] == 3){
                            $tax = $taxloop['service_price'];
                        }
                        if($taxloop['service_id'] == 4){
                            $tax_up = $taxloop['service_price'];
                        }
                    }
                }
                $total_tax += $tax + $tax_up;
            ?>
            <tr>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($No++); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($dataloop['no'] ?? '-'); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($dataloop['inspection_vehicles'][0]['license_plate'] ?? '-'); ?></td>
                
                <td style="border: 1px solid black; text-align: center;">
                    <?php if(isset($dataloop['date'])): ?>
                        <?php
                            $date = date('d/m/', strtotime($dataloop['date']));
                            $year = date('Y', strtotime($dataloop['date'])) + 543;
                            echo $date . $year;
                        ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($type_document); ?></td>
                
                <td style="border: 1px solid black; text-align: center;">
                    <?php if(isset($dataloop['appointment'])): ?>
                        <?php
                            $date = date('d/m/', strtotime($dataloop['date']));
                            $year = date('Y', strtotime($dataloop['date'])) + 543;
                            echo $date . $year;
                        ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($tax); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($tax_up); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($tax + $tax_up); ?></td>
            </tr>

        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td colspan="6" style="border: 1px solid black; text-align: right; font-weight: bold;">รวมทั้งสิ้น</td>
            <td colspan="3" style="border: 1px solid black; text-align: center; font-weight: bold;"><?php echo e($total_tax); ?></td>
        </tr>
    </table>
<?php endif; ?>
<?php if($tax_vehicle == 'complete'): ?>
    <table >
        <tr>
            <td colspan="12" style="text-align: center; font-size: 14px; font-weight: bold;">รายงานบันทึกภาษี วันที่ <?php echo e($start_date); ?> - <?php echo e($end_date); ?></td>
        </tr>
        <tr>
            <th style="border: 1px solid black; text-align: center; width: 100px;">ลำดับ</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เลขที่</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">ทะเบียน</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เบอร์โทร</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">วันรับงาน</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">นัดรับป้าย</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เอกสาร</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เรียกเก็บ</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">จ่ายจริง</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">คืนเงิน</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เพิ่มเงิน</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">ค่าธรรมเนียม</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">สถานะ</th>
        </tr>
        <?php
            $No = 1;
            $total_tax = 0;
            $total_paytrue = 0;
            $total_payup = 0 ;
            $total_paydown = 0 ;
            $total_commission = 0;
            $total = 0;
        ?>
        <?php $__currentLoopData = $data; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $num => $dataloop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $status = '';
                if($dataloop['status'] == 'finish'){
                    $status = 'สำเร็จ';
                }else{
                    $status = 'รอรับป้าย';
                }
                $type_document = '';
                if($dataloop['inspection_vehicles'][0]['type_document'] == 'copy'){
                    $type_document = 'สำเนา';
                }else {
                    $type_document = 'เล่ม';
                }

                $tax = 0;
                foreach ($dataloop['inspection_vehicles'] as $inspection_vehicles) {
                    foreach ($inspection_vehicles['vehicle_service_transaction'] as $taxloop) {
                        if($taxloop['service_id'] == 3){
                            $tax += $taxloop['service_price'];
                        }
                        if($taxloop['service_id'] == 4){
                            $tax += $taxloop['service_price'];
                        }
                    }
                }
                $total_tax += $tax;
                $total_paytrue += $dataloop['paytrue'];
                $total_commission += $dataloop['commission'];

                $payup = 0 ;
                $paydown = 0 ;
                if($dataloop['paytrue']){
                    if( ($tax - $dataloop['paytrue']) > 0 ){
                        $payup = $tax - $dataloop['paytrue'] ;
                        $paydown = 0;
                        $total_payup += $tax - $dataloop['paytrue'] ;
                    }else if( ($tax - $dataloop['paytrue']) == 0 ){
                        $payup = 0;
                        $paydown = 0;
                    }else if( ($tax - $dataloop['paytrue']) < 0 ){
                        $payup = 0;
                        $paydown = $tax - $dataloop['paytrue'];
                        $total_paydown += $tax - $dataloop['paytrue'] ;
                    }
                }
                $total += $dataloop['paytrue'] + $dataloop['commission'] ;
            ?>
            <tr>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($No++); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($dataloop['no'] ?? '-'); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($dataloop['inspection_vehicles'][0]['license_plate'] ?? '-'); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($dataloop['inspection_vehicles'][0]['phone_number'] ?? '-'); ?></td>
                
                <td style="border: 1px solid black; text-align: center;">
                    <?php if(isset($dataloop['date'])): ?>
                        <?php
                            $date = date('d/m/', strtotime($dataloop['date']));
                            $year = date('Y', strtotime($dataloop['date'])) + 543;
                            echo $date . $year;
                        ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td style="border: 1px solid black; text-align: center;">
                    <?php if(isset($dataloop['appointment'])): ?>
                        <?php
                            $date = date('d/m/', strtotime($dataloop['date']));
                            $year = date('Y', strtotime($dataloop['date'])) + 543;
                            echo $date . $year;
                        ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($type_document); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($tax); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($dataloop['paytrue']); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($payup); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($paydown); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($dataloop['commission']); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($status); ?></td>
            </tr>

        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td colspan="7" style="border: 1px solid black; text-align: right; font-weight: bold;">รวม</td>
            <td style="border: 1px solid black; text-align: center; font-weight: bold;"><?php echo e($total_tax); ?></td>
            <td style="border: 1px solid black; text-align: center; font-weight: bold;"><?php echo e($total_paytrue); ?></td>
            <td style="border: 1px solid black; text-align: center; font-weight: bold;"><?php echo e(abs($total_payup)); ?></td>
            <td style="border: 1px solid black; text-align: center; font-weight: bold;"><?php echo e(abs($total_paydown)); ?></td>
            <td style="border: 1px solid black; text-align: center; font-weight: bold;"><?php echo e($total_commission); ?></td>
            <td style="border: 1px solid black; text-align: center; font-weight: bold;"></td>
        </tr>
        <tr>
            <td colspan="7" style="border: 1px solid black; text-align: right; font-weight: bold;">ยอดรวมจ่าย</td>
            <td colspan="6" style="border: 1px solid black; text-align: center; font-weight: bold;"><?php echo e($total); ?></td>
        </tr>
    </table>
<?php endif; ?>
<?php if($tax_vehicle == 'success'): ?>
    <table >
        <tr>
            <td colspan="12" style="text-align: center; font-size: 14px; font-weight: bold;">รายงานบันทึกภาษี วันที่ <?php echo e($start_date); ?> - <?php echo e($end_date); ?></td>
        </tr>
        <tr>
            <th style="border: 1px solid black; text-align: center; width: 100px;">ลำดับ</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เลขที่</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">ทะเบียน</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เบอร์โทร</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">วันรับงาน</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">วันที่ยื่นภาษี</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">นัดรับป้าย</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เอกสาร</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เรียกเก็บ</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">จ่ายจริง</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">คืนเงิน</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">เพิ่มเงิน</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">ค่าธรรมเนียม</th>
            <th style="border: 1px solid black; text-align: center; width: 100px;">สถานะ</th>
        </tr>
        <?php
            $No = 1;
            $total_tax = 0;
            $total_paytrue = 0;
            $total_payup = 0 ;
            $total_paydown = 0 ;
            $total_commission = 0;
            $total = 0;
        ?>
        <?php $__currentLoopData = $data; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $num => $dataloop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $status = '';
                if($dataloop['status'] == 'finish'){
                    $status = 'สำเร็จ';
                }else{
                    $status = 'รอรับป้าย';
                }
                $type_document = '';
                if($dataloop['inspection_vehicles'][0]['type_document'] == 'copy'){
                    $type_document = 'สำเนา';
                }else {
                    $type_document = 'เล่ม';
                }

                $tax = 0;
                foreach ($dataloop['inspection_vehicles'] as $inspection_vehicles) {
                    foreach ($inspection_vehicles['vehicle_service_transaction'] as $taxloop) {
                        if($taxloop['service_id'] == 3){
                            $tax += $taxloop['service_price'];
                        }
                        if($taxloop['service_id'] == 4){
                            $tax += $taxloop['service_price'];
                        }
                    }
                }
                $total_tax += $tax;
                $total_paytrue += $dataloop['paytrue'];
                $total_commission += $dataloop['commission'];

                $payup = 0 ;
                $paydown = 0 ;
                if($dataloop['paytrue']){
                    if( ($tax - $dataloop['paytrue']) > 0 ){
                        $payup = $tax - $dataloop['paytrue'] ;
                        $paydown = 0;
                        $total_payup += $tax - $dataloop['paytrue'] ;
                    }else if( ($tax - $dataloop['paytrue']) == 0 ){
                        $payup = 0;
                        $paydown = 0;
                    }else if( ($tax - $dataloop['paytrue']) < 0 ){
                        $payup = 0;
                        $paydown = $tax - $dataloop['paytrue'];
                        $total_paydown += $tax - $dataloop['paytrue'] ;
                    }
                }
                $total += $dataloop['paytrue'] + $dataloop['commission'] ;
            ?>
            <tr>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($No++); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($dataloop['no'] ?? '-'); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($dataloop['inspection_vehicles'][0]['license_plate'] ?? '-'); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($dataloop['inspection_vehicles'][0]['phone_number'] ?? '-'); ?></td>
                
                <td style="border: 1px solid black; text-align: center;">
                    <?php if(isset($dataloop['date'])): ?>
                        <?php
                            $date = date('d/m/', strtotime($dataloop['date']));
                            $year = date('Y', strtotime($dataloop['date'])) + 543;
                            echo $date . $year;
                        ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td style="border: 1px solid black; text-align: center;">
                    <?php if(isset($dataloop['inspection_vehicles'][0]['vehicle']['tax_renewal_date'])): ?>
                        <?php
                            $date = date('d/m/', strtotime($dataloop['inspection_vehicles'][0]['vehicle']['tax_renewal_date']));
                            $year = date('Y', strtotime($dataloop['inspection_vehicles'][0]['vehicle']['tax_renewal_date'])) + 543;
                            echo $date . $year;
                        ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td style="border: 1px solid black; text-align: center;">
                    <?php if(isset($dataloop['appointment'])): ?>
                        <?php
                            $date = date('d/m/', strtotime($dataloop['date']));
                            $year = date('Y', strtotime($dataloop['date'])) + 543;
                            echo $date . $year;
                        ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($type_document); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($tax); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($dataloop['paytrue']); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($payup); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($paydown); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($dataloop['commission']); ?></td>
                <td style="border: 1px solid black; text-align: center;"><?php echo e($status); ?></td>
            </tr>

        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td colspan="8" style="border: 1px solid black; text-align: right; font-weight: bold;">รวม</td>
            <td style="border: 1px solid black; text-align: center; font-weight: bold;"><?php echo e($total_tax); ?></td>
            <td style="border: 1px solid black; text-align: center; font-weight: bold;"><?php echo e($total_paytrue); ?></td>
            <td style="border: 1px solid black; text-align: center; font-weight: bold;"><?php echo e(abs($total_payup)); ?></td>
            <td style="border: 1px solid black; text-align: center; font-weight: bold;"><?php echo e(abs($total_paydown)); ?></td>
            <td style="border: 1px solid black; text-align: center; font-weight: bold;"><?php echo e($total_commission); ?></td>
            <td style="border: 1px solid black; text-align: center; font-weight: bold;"></td>
        </tr>
        <tr>
            <td colspan="8" style="border: 1px solid black; text-align: right; font-weight: bold;">ยอดรวมจ่าย</td>
            <td colspan="6" style="border: 1px solid black; text-align: center; font-weight: bold;"><?php echo e($total); ?></td>
        </tr>
    </table>
<?php endif; ?>

<?php /**PATH /var/www/vhosts/dev-asha9.com/fie-api-uat.dev-asha9.com/resources/views/export/ReportCarTaxVehicle.blade.php ENDPATH**/ ?>