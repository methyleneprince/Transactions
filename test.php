<?php

include 'FieldGen.php';
$fieldGen = new FieldGen();
$fieldGen->lbls = array(
    'TransactionDate'   => 'Date of Transaction', 
    'PaymentDate'       => 'Date of Payment',
    'ResponsibleParty'  => 'Party responsible for transaction',
    'AssociatedParty'   => 'Party associated with transaction',
    'Inflow'            => 'Payment Type',
    'StatusID'          => 'Status'
);
echo serialize($fieldGen->lbls);
