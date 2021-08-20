# WHERE Syntax

Some of Medoo functions are required the $where argument to filter records like SQL WHERE clause, which is powerful but
with a lot of complex syntaxes, logical relativity, and potential security problems about SQL injection. But Medoo
provided a powerful and easy way to build a WHERE query clause and prevent injection.

## Basic Condition

The basic condition is simple enough to understand. You can use an additional symbol to get an advanced filter range for
numbers.

````injectablephp
$database->select("account", "user_name", [
    "email" => "foo@bar.com"
]);
// WHERE email = 'foo@bar.com'

$database->select("account", "user_name", [
    "user_id" => 200
]);
// WHERE user_id = 200

$database->select("account", "user_name", [
    "user_id[>]" => 200
]);
// WHERE user_id > 200

$database->select("account", "user_name", [
    "user_id[>=]" => 200
]);
// WHERE user_id >= 200

$database->select("account", "user_name", [
    "user_id[!]" => 200
]);
// WHERE user_id != 200

$database->select("account", "user_name", [
    "age[<>]" => [200, 500]
]);
// WHERE age BETWEEN 200 AND 500

$database->select("account", "user_name", [
    "age[><]" => [200, 500]
]);
// WHERE age NOT BETWEEN 200 AND 500
````

#### [><] and [<>] is also available for datetime.

~~~injectablephp
$database->select("account", "user_name", [
    "birthday[<>]" => [date("Y-m-d", mktime(0, 0, 0, 1, 1, 2015)), date("Y-m-d")]
]);

$database->select("account", "user_name", [
    "birthday[><]" => [date("Y-m-d", mktime(0, 0, 0, 1, 1, 2015)), date("Y-m-d")]
]);
~~~

````sql
WHERE ("birthday" BETWEEN '2015-01-01' AND '2017-01-01')
WHERE ("birthday" NOT BETWEEN '2015-01-01' AND '2017-01-01')
````

#### You can use not only single string or number value, but also array

````injectablephp
$database->select("account", "user_name", [
    "OR" => [
        "user_id" => [2, 123, 234, 54],
        "email" => ["foo@bar.com", "cat@dog.com", "admin@medoo.in"]
    ]
]);
````

```sql
WHERE
user_id IN (2,123,234,54) OR
email IN ('foo@bar.com','cat@dog.com','admin@medoo.in')
```

### Negative condition

````injectablephp
$database->select("account", "user_name", [
    "AND" => [
        "user_name[!]" => "foo",
        "user_id[!]" => 1024,
        "email[!]" => ["foo@bar.com", "cat@dog.com", "admin@medoo.in"],
        "city[!]" => null,
        "promoted[!]" => true
    ]
]);
````

```sql
WHERE
"user_name" != 'foo' AND
"user_id" != 1024 AND
"email" NOT IN ('foo@bar.com','cat@dog.com','admin@medoo.in') AND
"city" IS NOT NULL
"promoted" != 1
```

### Or fetched from select() or get() function.

```injectablephp
$database->select("account", "user_name", [
    "user_id" => $database->select("post", "user_id", ["comments[>]" => 40])
]);
```

```sql
WHERE user_id IN (2, 51, 321, 3431)
```

### Relativity Condition

The relativity condition can describe the complex relationship between data and data. You can use AND and OR to build
complex relativity condition queries.

### Basic

```injectablephp
$database->select("account", "user_name", [
    "AND" => [
        "user_id[>]" => 200,
        "age[<>]" => [18, 25],
        "gender" => "female"
    ]
]);
// Medoo will connect the relativity condition with AND by default. The following usage is the same as above.
$database->select("account", "user_name", [
    "user_id[>]" => 200,
    "age[<>]" => [18, 25],
    "gender" => "female"
]);
```

```sql
WHERE user_id > 200 AND age BETWEEN 18 AND 25 AND gender = 'female'
```

```injectablephp
$database->select("account", "user_name", [
    "OR" => [
        "user_id[>]" => 200,
        "age[<>]" => [18, 25],
        "gender" => "female"
    ]
]);
```

```sql
WHERE user_id > 200 OR age BETWEEN 18 AND 25 OR gender = 'female'
```

### Compound

```injectablephp
$database->has("account", [
    "AND" => [
        "OR" => [
            "user_name" => "foo",
            "email" => "foo@bar.com"
        ],
        "password" => "12345"
    ]
]);
```

```sql
WHERE (user_name = 'foo' OR email = 'foo@bar.com') AND password = '12345'
```

#### Because Medoo uses array data construction to describe the relativity condition, arrays with duplicate keys will be overwritten.

```injectablephp
// This will be error:
$database->select("account", '*', [
    "AND" => [
        "OR" => [
            "user_name" => "foo",
            "email" => "foo@bar.com"
        ],
        "OR" => [
            "user_name" => "bar",
            "email" => "bar@foo.com"
        ]
    ]
]);
// [X] SELECT * FROM "account" WHERE ("user_name" = 'bar' OR "email" = 'bar@foo.com')
````

#### To correct that, just assign a comment for each AND and OR key name (# with a blank space). The comment content can be everything.

```injectablephp

$database->select("account", '*', [
    "AND #Actually, this comment feature can be used on every AND and OR relativity condition" => [
        "OR #the first condition" => [
            "user_name" => "foo",
            "email" => "foo@bar.com"
        ],
        "OR #the second condition" => [
            "user_name" => "bar",
            "email" => "bar@foo.com"
        ]
    ]
]);
```
```sql
WHERE (
("user_name" = 'foo' OR "email" = 'foo@bar.com')
AND
("user_name" = 'bar' OR "email" = 'bar@foo.com')
)
```

### Columns Relationship 
```injectablephp
$database->select("post", [
    "[>]account" => "user_id",
    ], [
    "post.content"
    ], [
    // Connect two columns with condition signs like [=], [>], [<], [!=] as one of array value.
    "post.restrict[<]account.age"
]); 
```

```sql
WHERE "post"."restrict" < "account"."age"
```

### LIKE Condition 
LIKE condition can be used like basic condition or relativity condition with just adding [~] syntax.

```injectablephp
// By default, the keyword will be quoted with % front and end to match the whole word.
$database->select("person", "id", [
    "city[~]" => "lon"
]);
```
```sql
 WHERE "city" LIKE '%lon%' 
```

### Group
```injectablephp
$database->select("person", "id", [
    "city[~]" => ["lon", "foo", "bar"]
]);
```
```sql
WHERE "city" LIKE '%lon%' OR "city" LIKE '%foo%' OR "city" LIKE '%bar%' 
```

### Negative condition 

```injectablephp
$database->select("person", "id", [
    "city[!~]" => "lon"
]); 
```
```sql
WHERE "city" NOT LIKE '%lon%'
```

### Compound 
```injectablephp
$database->select("person", "id", [
    "content[~]" => ["AND" => ["lon", "on"]]
]);
```
```sql
WHERE ("content" LIKE '%lon%' AND "content" LIKE '%on%')
```
```injectablephp
$database->select("person", "id", [
    "content[~]" => ["OR" => ["lon", "on"]]
]); 
```
```sql
WHERE ("content" LIKE '%lon%' OR "content" LIKE '%on%')
```

### SQL Wildcard 
You can use SQL wildcard to match more complex situation.

```injectablephp
$database->select("person", "id", [
    "city[~]" => "%stan" // Kazakhstan, Uzbekistan, Türkmenistan
]);

$database->select("person", "id", [
    "city[~]" => "Londo_" // London, Londox, Londos...
]);

$database->select("person", "id", [
    "name[~]" => "[BCR]at" // Bat, Cat, Rat
]);

$database->select("person", "id", [
    "name[~]" => "[!BCR]at" // Eat, Fat, Hat...
]);
```
 
### Order Condition 

```injectablephp
$database->select("account", "user_id", [
    // Single condition.
    "ORDER" => "user_id",

	// Multiple condition.
	"ORDER" => [
		// Order by column with sorting by custom order.
		"user_id" => [43, 12, 57, 98, 144, 1],
 
		// Order by column.
		"register_date",
 
		// Order by column with descending sorting.
		"profile_id" => "DESC",
 
		// Order by column with ascending sorting.
		"date" => "ASC"
	]

]);
```


### Full Text Searching
Full-text searching feature is supported by MySQL database for an advanced 
search result.

#### Search mode 

|- | -|
|:---:|:---:|
|list natural| IN NATURAL LANGUAGE MODE|
|natural+query| IN NATURAL LANGUAGE MODE WITH QUERY EXPANSION|
|boolean| IN BOOLEAN MODE|
|query| WITH QUERY EXPANSION|

```injectablephp
// [MATCH]
$database->select("post_table", "post_id", [
    "MATCH" => [
    "columns" => ["content", "title"],
    "keyword" => "foo",
		// [optional] Search mode.
	    "mode" => "natural"
	]
]); 
```

```sql
WHERE MATCH (content, title) AGAINST ('foo' IN NATURAL LANGUAGE MODE)
```

### Using Regular Expression
```injectablephp
$data = $database->select('account', [
    'user_id',
    'user_name'
    ], [
    'user_name[REGEXP]' => '[a-z0-9]*'
]); 
```
```sql
WHERE "user_name" REGEXP '[a-z0-9]*'
```

### Using SQL Functions 
You can now use SQL functions with the raw object for complex usage. 
Read more
from https://medoo.in/api/raw.
```injectablephp
$data = $database->select('account', [
    'user_id',
    'user_name'
    ], [
    'datetime' => Medoo::raw('NOW()')
]); 
```

```sql
WHERE "datetime" = NOW()
```

### LIMIT And OFFSET 
```injectablephp
$database->select("account", "user_id", [
    // Get the first 100 of rows.
    'LIMIT' => 100
    
	// Start from the top 20 rows and get the next 100.
	'LIMIT' => [20, 100],
 
	// For Oracle and MSSQL databases, you also need to use ORDER BY together.
	'ORDER' => 'location'

]); 
```
### GROUP And HAVING 
```injectablephp
$database->select("account", "user_id", [
    'GROUP' => 'type',

	// GROUP by array of values.
	'GROUP' => [
		'type',
		'age',
		'gender'
	],
 
	'HAVING' => [
		'user_id[>]' => 500
	]

]);
```