<?php

class PagoFacil_ExternalPayment_Model_Environmentselection
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'DEVELOPMENT',
                'label' => 'Integration, tests and development',
            ),
            array(
                'value' => 'PRODUCTION',
                'label' => 'Production',
            )
        );
    }
}

?>