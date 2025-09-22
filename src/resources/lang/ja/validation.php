<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attributeを承認してください。',
    'accepted_if' => ':otherが:valueの場合、:attributeを承認する必要があります。',
    'active_url' => ':attributeは、有効なURLではありません。',
    'after' => ':attributeは、:dateより後の日付である必要があります。',
    'after_or_equal' => ':attributeは、:date以降の日付である必要があります。',
    'alpha' => ':attributeは、文字のみを含むことができます。',
    'alpha_dash' => ':attributeは、文字、数字、ダッシュ、アンダースコアのみを含むことができます。',
    'alpha_num' => ':attributeは、文字と数字のみを含むことができます。',
    'array' => ':attributeは、配列である必要があります。',
    'before' => ':attributeは、:dateより前の日付である必要があります。',
    'before_or_equal' => ':attributeは、:date以前の日付である必要があります。',
    'between' => [
        'numeric' => ':attributeは、:minと:maxの間にある必要があります。',
        'file' => ':attributeは、:minキロバイトから:maxキロバイトの間にある必要があります。',
        'string' => ':attributeは、:min文字から:max文字の間にある必要があります。',
        'array' => ':attributeは、:min個から:max個のアイテムを持つ必要があります。',
    ],
    'boolean' => ':attributeフィールドは、trueまたはfalseである必要があります。',
    'confirmed' => ':attributeの確認が一致しません。',
    'current_password' => 'パスワードが正しくありません。',
    'date' => ':attributeは、有効な日付ではありません。',
    'date_equals' => ':attributeは、:dateと同じ日付である必要があります。',
    'date_format' => ':attributeは、:format形式と一致しません。',
    'declined' => ':attributeを拒否する必要があります。',
    'declined_if' => ':otherが:valueの場合、:attributeを拒否する必要があります。',
    'different' => ':attributeと:otherは、異なる必要があります。',
    'digits' => ':attributeは、:digits桁である必要があります。',
    'digits_between' => ':attributeは、:min桁から:max桁の間である必要があります。',
    'dimensions' => ':attributeの画像サイズが無効です。',
    'distinct' => ':attributeフィールドには、重複する値があります。',
    'email' => ':attributeは、有効なメールアドレスである必要があります。',
    'ends_with' => ':attributeは、次のいずれかで終わる必要があります: :values。',
    'enum' => '選択された:attributeは無効です。',
    'exists' => '選択された:attributeは無効です。',
    'file' => ':attributeは、ファイルである必要があります。',
    'filled' => ':attributeフィールドは、値を持つ必要があります。',
    'gt' => [
        'numeric' => ':attributeは、:valueより大きい必要があります。',
        'file' => ':attributeは、:valueキロバイトより大きい必要があります。',
        'string' => ':attributeは、:value文字より大きい必要があります。',
        'array' => ':attributeは、:valueより多くのアイテムを持つ必要があります。',
    ],
    'gte' => [
        'numeric' => ':attributeは、:value以上である必要があります。',
        'file' => ':attributeは、:valueキロバイト以上である必要があります。',
        'string' => ':attributeは、:value文字以上である必要があります。',
        'array' => ':attributeは、:value個以上のアイテムを持つ必要があります。',
    ],
    'image' => ':attributeは、画像である必要があります。',
    'in' => '選択された:attributeは無効です。',
    'in_array' => ':attributeフィールドは、:other内に存在しません。',
    'integer' => ':attributeは、整数である必要があります。',
    'ip' => ':attributeは、有効なIPアドレスである必要があります。',
    'ipv4' => ':attributeは、有効なIPv4アドレスである必要があります。',
    'ipv6' => ':attributeは、有効なIPv6アドレスである必要があります。',
    'json' => ':attributeは、有効なJSON文字列である必要があります。',
    'lt' => [
        'numeric' => ':attributeは、:valueより小さい必要があります。',
        'file' => ':attributeは、:valueキロバイトより小さい必要があります。',
        'string' => ':attributeは、:value文字より小さい必要があります。',
        'array' => ':attributeは、:valueより少ないアイテムを持つ必要があります。',
    ],
    'lte' => [
        'numeric' => ':attributeは、:value以下である必要があります。',
        'file' => ':attributeは、:valueキロバイト以下である必要があります。',
        'string' => ':attributeは、:value文字以下である必要があります。',
        'array' => ':attributeは、:value個以下のアイテムを持つ必要があります。',
    ],
    'mac_address' => ':attributeは、有効なMACアドレスである必要があります。',
    'max' => [
        'numeric' => ':attributeは、:maxより大きくない必要があります。',
        'file' => ':attributeは、:maxキロバイトより大きくない必要があります。',
        'string' => ':attributeは、:max文字より大きくない必要があります。',
        'array' => ':attributeは、:max個より多くのアイテムを持つことはできません。',
    ],
    'mimes' => ':attributeは、:valuesタイプのファイルである必要があります。',
    'mimetypes' => ':attributeは、:valuesタイプのファイルである必要があります。',
    'min' => [
        'numeric' => ':attributeは、少なくとも:minである必要があります。',
        'file' => ':attributeは、少なくとも:minキロバイトである必要があります。',
        'string' => ':attributeは、少なくとも:min文字である必要があります。',
        'array' => ':attributeは、少なくとも:min個のアイテムを持つ必要があります。',
    ],
    'multiple_of' => ':attributeは、:valueの倍数である必要があります。',
    'not_in' => '選択された:attributeは無効です。',
    'not_regex' => ':attributeの形式が無効です。',
    'numeric' => ':attributeは、数字である必要があります。',
    'password' => 'パスワードが正しくありません。',
    'present' => ':attributeフィールドは、存在している必要があります。',
    'prohibited' => ':attributeフィールドは禁止されています。',
    'prohibited_if' => ':otherが:valueの場合、:attributeフィールドは禁止されています。',
    'prohibited_unless' => ':otherが:valuesにない限り、:attributeフィールドは禁止されています。',
    'prohibits' => ':attributeフィールドは、:otherの存在を禁止しています。',
    'regex' => ':attributeの形式が無効です。',
    'required' => ':attributeは必須です。',
    'required_array_keys' => ':attributeフィールドには、:valuesのエントリが含まれている必要があります。',
    'required_if' => ':otherが:valueの場合、:attributeフィールドは必須です。',
    'required_unless' => ':otherが:valuesにない限り、:attributeフィールドは必須です。',
    'required_with' => ':valuesが存在する場合、:attributeフィールドは必須です。',
    'required_with_all' => ':valuesがすべて存在する場合、:attributeフィールドは必須です。',
    'required_without' => ':valuesが存在しない場合、:attributeフィールドは必須です。',
    'required_without_all' => ':valuesがすべて存在しない場合、:attributeフィールドは必須です。',
    'same' => ':attributeと:otherは、一致している必要があります。',
    'size' => [
        'numeric' => ':attributeは、:sizeである必要があります。',
        'file' => ':attributeは、:sizeキロバイトである必要があります。',
        'string' => ':attributeは、:size文字である必要があります。',
        'array' => ':attributeは、:size個のアイテムを含む必要があります。',
    ],
    'starts_with' => ':attributeは、次のいずれかで始まる必要があります: :values。',
    'string' => ':attributeは、文字列である必要があります。',
    'timezone' => ':attributeは、有効なタイムゾーンである必要があります。',
    'unique' => ':attributeは、すでに使用されています。',
    'uploaded' => ':attributeのアップロードに失敗しました。',
    'url' => ':attributeは、有効なURLである必要があります。',
    'uuid' => ':attributeは、有効なUUIDである必要があります。',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'name' => '名前',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'password_confirmation' => 'パスワード（確認用）',
    ],

];
