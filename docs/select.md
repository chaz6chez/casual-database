## Select
Select data from the table.
```injectablephp
select($table, $columns)
```

#### table [string]
The table name.

#### columns [string/array]
The target columns of data will be fetched.
```injectablephp
select($table, $columns, $where)
```

#### table [string]
The table name.

#### columns [string/array]
The target columns of data will be fetched.

#### where (optional) [array]
The WHERE clause to filter records.
~~~injectablephp
select($table, $join, $columns, $where)
~~~
#### table [string]
The table name.

#### join [array]
Table relativity for table joining. Ignore it if no table joining required.

#### columns [string/array]
The target columns of data will be fetched.

#### where (optional) [array]
The WHERE clause to filter records.

#### Return: [array]
You can use * as columns parameter to fetch all columns, but we recommended providing all targeted columns for better performance and readability.

~~~injectablephp
$data = $database->select("account", [
    "user_name",
    "email"
    ], [
    "user_id[>]" => 100
]);

// $data = array(
//  [0] => array(
//	  "user_name" => "foo",
//	  "email" => "foo@bar.com"
//  ),
//  [1] => array(
//	  "user_name" => "cat",
//	  "email" => "cat@dog.com"
//  )
// )

foreach($data as $item) {
    echo "user_name:" . $item["user_name"] . " - email:" . $item["email"] . "<br/>";
}

// Select all columns.
$data = $database->select("account", "*");

// Select a column.
$data = $database->select("account", "user_name");

// $data = array(
//  [0] => "foo",
//  [1] => "cat"
// )
~~~

### Traverse Fetch With Callback
While fetching data from a database, data will be first loaded into memory as an array variable and output back to the frontend.

If fetching a large amount of data from a database, the memory will be exhausted.

When passing the callback closure function ($data) {} as the last parameter for select(), it will output each data immediately without loading into memory. That will be having a better performance for loading large amounts of data.

~~~injectablephp
$database->select("account", ["name"], function ($data) {
    echo $data["name"];
});

$database->select("account", [
    "name"
], function ($data) {
    echo $data["name"];
});
~~~

### Performance Benchmark
Fetching 1,000, 5,000 and 20,000 different name data from MySQL database, and output it. Get memory usage via memory_get_usage().

~~~injectablephp
// Method 1
$database->select("account", ["name"], function ($data) {
    echo $data["name"];
});

// vs

// Method 2
$data = $database->select("account", ["name"]);

foreach ($data as $item) {
    echo $item["name"];
}
~~~

|-|Method 1|	Method 2|
|:---:|:---:|:---:|
|1,000 Records|	789 KB|	1.2 MB|
|5,000 Records|	1.1 MB|	3.3 MB|
|20,000 Records|	2.26 MB|	11.1 MB|

### Table Joining
SQL JOIN clause can combine rows together between two tables. Medoo provides a simple syntax for the JOIN clause.

- [>] ==> LEFT JOIN
- [<] ==> RIGHT JOIN
- [<>] ==> FULL JOIN
- [><] ==> INNER JOIN

~~~injectablephp
$database->select("post", [
// Here is the table relativity argument that tells the relativity between the table you want to join.
"[>]account" => ["author_id" => "user_id"]
], [
"post.title",
"account.city"
]);
~~~

The row author_id from table post is equal the row user_id from table account.

~~~injectablephp
"[>]account" => ["author_id" => "user_id"]
~~~

~~~sql
LEFT JOIN "account" ON "post"."author_id" = "account"."user_id"
~~~

The row user_id from table post is equal to the row user_id from table album.

This is a shortcut to declare relativity if the row names are the same in both tables.

~~~injectablephp
"[>]album" => "user_id"
~~~

~~~sql
LEFT JOIN "album" USING ("user_id")
~~~

post.user_id is equal photo.user_id and post.avatar_id is equal photo.avatar_id

Like above, there are two rows or more that are the same in both tables.

~~~injectablephp
"[>]photo" => ["user_id", "avatar_id"]
~~~
~~~sql
LEFT JOIN "photo" USING ("user_id", "avatar_id")
~~~
If you want to join the same table with different values, you have to assign the table with an alias.
~~~injectablephp
"[>]account (replier)" => ["replier_id" => "user_id"]
~~~
~~~sql
LEFT JOIN "account" AS "replier" ON "post"."replier_id" = "replier"."user_id"
~~~
You can refer to the previously joined table by adding the table name before the column.
~~~injectablephp
"[>]account" => ["author_id" => "user_id"],
"[>]album" => ["account.user_id" => "user_id"]
~~~
~~~sql
LEFT JOIN "account" ON "post"."author_id" = "account"."user_id"
LEFT JOIN "album" ON "account"."user_id" = "album"."user_id"
~~~
### Multiple Conditions
~~~injectablephp
"[>]account" => [
    "author_id" => "user_id",
    "album.user_id" => "user_id"
]
~~~
~~~sql
LEFT JOIN "account" ON
"account"."author_id" = "account"."user_id" AND
"album"."user_id" = "account"."user_id"
~~~
### Additional Condition
~~~injectablephp
"[>]comment" => [
    "author_id" => "user_id",
    "AND" => [
        "rate[>]" => 50
    ]
]
~~~
~~~sql
LEFT JOIN "comment" ON "account"."author_id" = "comment"."user_id" AND "rate" > 50
~~~
### Join with Raw Object
~~~injectablephp
"[>]account" => Medoo::raw("ON <post.author_id> = <account.user_id>")
~~~
~~~sql
LEFT JOIN "account" ON "post"."author_id" = "account"."user_id"
~~~
### Data Mapping
Customize output data construction - The key name for wrapping data has no relation with columns themselves, and it is multidimensional.
~~~injectablephp
$data = $database->select("post", [
    "[>]account" => ["user_id"]
    ], [
    "post.content",

	"userData" => [
		"account.user_id",
		"account.email",
 
		"meta" => [
			"account.location",
			"account.gender"
		]
	]
], [
    "LIMIT" => [0, 2]
]);

echo json_encode($data);
~~~
~~~json
[{
    content: "Hello world!",
    userData: {
        user_id: "1",
        email: "foo@example.com",
        meta: {
          location: "New York",
          gender: "male"
        }
    }
}, {
    content: "Hey everyone",
    userData: {
      user_id: "2",
      email: "bar@example.com",
      meta: {
        location: "London",
        gender: "female"
      }
    }
}]
~~~
### Index Mapping
Setting the column as the first key name of the column parameter, the result will be indexed by this name.
~~~injectablephp
$data = $database->select("post", [
    "user_id" => [
        "nickname",
        "location",
        "email"
    ]
]);
~~~
~~~json
[
    10: {
        nickname: "foo",
        location: "New York",
        email: "foo@example.com"
    },
    12: {
        nickname: "bar",
        location: "New York",
        email: "bar@medoo.in"   
    }
]
~~~
### Data Type Declaration
Set the type of output data.
~~~injectablephp
// Supported data type: [String | Bool | Int | Number | Object | JSON]
// [String] is the default type for all output data.
// [Object] is a PHP object data decoded by serialize(), and will be unserialize()
// [JSON] is a valid JSON, and will be json_decode()

$data = $database->select("post", [
"[>]account" => ["user_id"]
], [
"post.post_id",

	"profile" => [
		"account.age [Int]",
		"account.is_locked [Bool]",
		"account.userData [JSON]"
	]
]);

echo json_encode($data);
~~~
~~~json
[{
  post_id: "1",
  profile: {
    age: 20,
    is_locked: true,
    userData: ["foo", "bar", "tim"]
  }
}, {
  post_id: "2",
  profile: {
    age: 25,
    is_locked: false,
    userData: ["mydata1", "mydata2"]
  }
}]
~~~

~~~injectablephp
// Store an object into database, and get it back.
class Foo {
var $bar = "cat";

	public function __wakeup()
	{
		$this->bar = "dog";
	}
}

$object_data = new Foo();

$database->insert("account", [
"data" => $object_data
]);

$data = $database->select("account", [
"data [object]"
]);

echo $data[0]["data"]->bar;

// The object\'s __wakeup function will be called and update the value.
// So the output will be "dog".
"dog"
~~~
### Alias
You can use the alias as a new column or table name instead of the original one. This is useful for table joining to prevent name conflict.

~~~injectablephp
$data = $database->select("account", [
    "user_id",
    "nickname (my_nickname)"
]);

// $data = array(
//  [0] => array(
//	  "user_id" => "1",
//	  "my_nickname" => "foo"
//  ),
//  [1] => array(
//	  "user_id" => "2",
//	  "my_nickname" => "bar"
//  )
// )

$data = $database->select("post (content)", [
    "[>]account (user)" => "user_id",
], [
    "content.user_id (author_id)",
    "user.user_id"
]);

// $data = array(
//  [0] => array(
//	  "author_id" => "1",
//	  "user_id" => "321"
//  ),
//  [1] => array(
//	  "author_id" => "2",
//	  "user_id" => "322"
//  )
// )
~~~
~~~sql
SELECT
    "content"."user_id" AS author_id,
    "user"."user_id"
FROM
    "post" AS "content"
LEFT JOIN "account" AS "user" USING ("user_id")
~~~
### Distinct
To add a distinct keyword to the column, you can put @ in front of the column name with any order.

~~~injectablephp
$data = $database->select("account", [
    "id",
    "name",
// The location with @ sign, will pop up to the top.
    "@location"
]);
~~~
~~~sql
SELECT DISTINCT "location","id", "name"
FROM "account"
~~~
To get the count number with distinct, you can use it with the raw object.
~~~injectablephp
$data = $database->select("account", [
    "unique_locations" => Medoo::raw("COUNT(DISTINCT <location>)")
]);
~~~
~~~sql
SELECT COUNT(DISTINCT "location") AS "unique_locations"
FROM "account"
~~~