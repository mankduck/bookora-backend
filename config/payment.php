<?php

return [
    'bank' => [
        'name' =>
            env(
                'BOOKORA_BANK_NAME',
                'MB Bank'
            ),

        /*
         * VietQR dùng mã BIN / mã ngân hàng,
         * ví dụ MB = MB, VCB = VCB, ACB = ACB.
         */
        'code' =>
            env(
                'BOOKORA_BANK_CODE',
                'MB'
            ),

        'account_number' =>
            env(
                'BOOKORA_BANK_ACCOUNT_NUMBER',
                ''
            ),

        'account_name' =>
            env(
                'BOOKORA_BANK_ACCOUNT_NAME',
                ''
            ),

        'transfer_prefix' =>
            env(
                'BOOKORA_TRANSFER_PREFIX',
                'BOOKORA'
            ),
    ],
];
