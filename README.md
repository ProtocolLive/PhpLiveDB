## Why PhpLiveDB?
Using large querys with PDO, you have a lot of fields and placeholders... Using insert with ordered placeholders, it's worse, because you don't know which placeholder is which field...
```php
$pdo->prepare("
  insert into users('id', 'name', 'email', 'password', 'address', 'country', 'zipcode', 'telephone', 'gender', 'register_date', 'last_login', 'last_pass_change')
  values(?,?,?,?,?,?,?,?,?,?,?,?)
");
```
That's a simple example. We know there are a lot of bigger queries out there... What if I need to add a new field? Just add the field name and one more placeholder. What if PDO throws an error saying different placeholder fields and numbers? I'd have to count them one by one...

Named placeholders help with this. Okay, but that's a lot of lines to write and manage... This library helps with that:

```php
$db->Insert('users')
->FieldAdd('id', $id, Types::Int)
->FieldAdd('name', $name, Types::Str)
->FieldAdd('email', $email, Types::Str)
->FieldAdd('password', $password, Types::Str)
->FieldAdd('address', $address, Types::Str)
->FieldAdd('country', $country, Types::Str)
->FieldAdd('zipcode', $zipcode, Types::Str)
->FieldAdd('telephone', $telephone, Types::Str)
->FieldAdd('gender', $gender, Types::Str)
->FieldAdd('register_date', $register_date, Types::Str)
->FieldAdd('last_login', $last_login, Types::Int)
->FieldAdd('last_pass_change', $last_pass_change, Types::Int)
->Run();
```

This was the initial idea of the library that was expanded according to my needs and today it is a very complete library (if not, feedbacks are welcome) that I use in my daily life and share with the world.

For the documentation, click [here](https://protocollive.github.io/PhpLiveDbDocs/en/).

## Install
- Download the library in zip format and use the _src_ folder content;
- Via Composer with `composer install protocollive/phplivedb`;
