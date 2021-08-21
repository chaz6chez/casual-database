<?php

namespace Database\Tests;

use Database\Driver;

/**
 * @coversDefaultClass \Database\Connection
 */
class ModelWhereTest extends ModelTestCase
{
    /**
     * @covers ::select()
     * @covers \Database\Driver::raw()
     * @covers ::field()
     * @covers ::where()
     * @dataProvider typesProvider
     */
    public function testBasicWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "email" => "foo@bar.com",
            "user_id" => 200,
            "user_id[>]" => 200,
            "user_id[>=]" => 200,
            "user_id[!]" => 200,
            "age[<>]" => [200, 500],
            "age[><]" => [200, 500],
            "income[>]" => Driver::raw("COUNT(<average>)"),
            "remote_id" => Driver::raw("UUID()"),
            "location" => null,
            "is_selected" => true
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            "email" = 'foo@bar.com' AND
            "user_id" = 200 AND
            "user_id" > 200 AND
            "user_id" >= 200 AND
            "user_id" != 200 AND
            ("age" BETWEEN 200 AND 500) AND
            ("age" NOT BETWEEN 200 AND 500) AND
            "income" > COUNT("average") AND
            "remote_id" = UUID() AND
            "location" IS NULL AND
            "is_selected" = 1
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @dataProvider typesProvider
     */
    public function testBetweenDateTimeWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "birthday[<>]" => [date("Y-m-d", mktime(0, 0, 0, 1, 1, 2015)), date("Y-m-d", mktime(0, 0, 0, 1, 1, 2045))]
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            ("birthday" BETWEEN '2015-01-01' AND '2045-01-01')
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @dataProvider typesProvider
     */
    public function testNotBetweenDateTimeWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "birthday[><]" => [date("Y-m-d", mktime(0, 0, 0, 1, 1, 2015)), date("Y-m-d", mktime(0, 0, 0, 1, 1, 2045))]
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            ("birthday" NOT BETWEEN '2015-01-01' AND '2045-01-01')
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @dataProvider typesProvider
     */
    public function testBetweenStringWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "location[<>]" => ['New York', 'Santo']
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            ("location" BETWEEN 'New York' AND 'Santo')
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @dataProvider typesProvider
     */
    public function testBetweenRawWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "birthday[<>]" => [
                Driver::raw("to_date(:from, 'YYYY-MM-DD')", [":from" => '2015/05/15']),
                Driver::raw("to_date(:to, 'YYYY-MM-DD')", [":to" => '2025/05/15'])
            ]
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            ("birthday" BETWEEN to_date('2015/05/15', 'YYYY-MM-DD') AND to_date('2025/05/15', 'YYYY-MM-DD'))
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @dataProvider typesProvider
     */
    public function testGreaterDateTimeWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "birthday[>]" => date("Y-m-d", mktime(0, 0, 0, 1, 1, 2045))
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE "birthday" > '2045-01-01'
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @dataProvider typesProvider
     */
    public function testArrayIntValuesWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "user_id" => [2, 123, 234, 54]
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            "user_id" IN (2, 123, 234, 54)
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @dataProvider typesProvider
     */
    public function testArrayStringValuesWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "email" => ["foo@bar.com", "cat@dog.com", "admin@medoo.in"]
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            "email" IN ('foo@bar.com', 'cat@dog.com', 'admin@medoo.in')
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @covers \Database\Driver::raw()
     * @dataProvider typesProvider
     */
    public function testNegativeWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "AND" => [
                "user_name[!]" => "foo",
                "user_id[!]" => 1024,
                "email[!]" => ["foo@bar.com", "admin@medoo.in"],
                "city[!]" => null,
                "promoted[!]" => true,
                "location[!]" => Driver::raw('LOWER("New York")')
            ]
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            ("user_name" != 'foo' AND
            "user_id" != 1024 AND
            "email" NOT IN ('foo@bar.com', 'admin@medoo.in') AND
            "city" IS NOT NULL AND
            "promoted" != 1 AND
            "location" != LOWER("New York"))
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @dataProvider typesProvider
     */
    public function testBasicAndRelativityWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "AND" => [
                "user_id[>]" => 200,
                "gender" => "female"
            ]
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            ("user_id" > 200 AND "gender" = 'female')
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @dataProvider typesProvider
     */
    public function testBasicSingleRelativityWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "user_id[>]" => 200,
            "gender" => "female"
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            "user_id" > 200 AND "gender" = 'female'
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @dataProvider typesProvider
     */
    public function testBasicOrRelativityWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "OR" => [
                "user_id[>]" => 200,
                "age[<>]" => [18, 25],
                "gender" => "female"
            ]
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            ("user_id" > 200 OR
            ("age" BETWEEN 18 AND 25) OR
            "gender" = 'female')
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @dataProvider typesProvider
     */
    public function testCompoundRelativityWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "AND" => [
                "OR" => [
                    "user_name" => "foo",
                    "email" => "foo@bar.com"
                ],
                "password" => "12345"
            ]
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            (("user_name" = 'foo' OR "email" = 'foo@bar.com') AND "password" = '12345')
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @dataProvider typesProvider
     */
    public function testCompoundDuplicatedKeysWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "AND #comment" => [
                "OR #first comment" => [
                    "user_name" => "foo",
                    "email" => "foo@bar.com"
                ],
                "OR #sencond comment" => [
                    "user_name" => "bar",
                    "email" => "bar@foo.com"
                ]
            ]
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            (("user_name" = 'foo' OR "email" = 'foo@bar.com') AND
            ("user_name" = 'bar' OR "email" = 'bar@foo.com'))
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @covers ::join()
     * @dataProvider typesProvider
     */
    public function testColumnsRelationshipWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table("post")->field("post.content")->join(["[>]demo" => "user_id"])->where([
            "post.restrict[<]demo.age"
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "post"."content"
            FROM "post"
            LEFT JOIN "demo"
            USING ("user_id")
            WHERE "post"."restrict" < "demo"."age"
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @dataProvider typesProvider
     */
    public function testBasicLikeWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "city[~]" => "lon"
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            ("city" LIKE '%lon%')
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @dataProvider typesProvider
     */
    public function testGroupedLikeWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "city[~]" => ["lon", "foo", "bar"]
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            ("city" LIKE '%lon%' OR
            "city" LIKE '%foo%' OR
            "city" LIKE '%bar%')
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::where()
     * @covers ::field()
     * @dataProvider typesProvider
     */
    public function testNegativeLikeWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "city[!~]" => "lon"
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            ("city" NOT LIKE '%lon%')
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @dataProvider typesProvider
     */
    public function testNonEscapeLikeWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "city[~]" => "some_where"
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            ("city" LIKE 'some_where')
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @dataProvider typesProvider
     */
    public function testEscapeLikeWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "city[~]" => "some\_where"
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            ("city" LIKE '%some\_where%')
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @dataProvider typesProvider
     */
    public function testCompoundLikeWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "content[~]" => ["AND" => ["lon", "on"]],
            "city[~]" => ["OR" => ["lon", "on"]]
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            ("content" LIKE '%lon%' AND "content" LIKE '%on%') AND
            ("city" LIKE '%lon%' OR "city" LIKE '%on%')
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @dataProvider typesProvider
     */
    public function testWildcardLikeWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "city[~]" => "%stan",
            "location[~]" => "Londo_",
            "name[~]" => "[BCR]at",
            "nickname[~]" => "[!BCR]at"
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE
            ("city" LIKE '%stan') AND
            ("location" LIKE 'Londo_') AND
            ("name" LIKE '[BCR]at') AND
            ("nickname" LIKE '[!BCR]at')
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::where()
     * @covers ::field()
     * @dataProvider typesProvider
     */
    public function testBasicOrderWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "ORDER" => "user_id"
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            ORDER BY "user_id"
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::where()
     * @covers ::field()
     * @dataProvider typesProvider
     */
    public function testMultipleOrderWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "ORDER" => [
                // Order by column with sorting by customized order.
                "user_id" => [43, 12, 57, 98, 144, 1],

                // Order by column.
                "register_date",

                // Order by column with descending sorting.
                "profile_id" => "DESC",

                // Order by column with ascending sorting.
                "date" => "ASC"
            ]
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            ORDER BY FIELD("user_id", 43,12,57,98,144,1),"register_date","profile_id" DESC,"date" ASC
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @covers \Database\Driver::raw
     * @dataProvider typesProvider
     */
    public function testOrderWithRawWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "ORDER" => Driver::raw("<location>, <gender>")
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            ORDER BY "location", "gender"
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::where()
     * @covers ::field()
     */
    public function testFullTextSearchWhere()
    {
        $this->database = $this->setType('mysql', true);

        $this->database->table($this->model->table())->field("user_name")->where([
            "MATCH" => [
                "columns" => ["content", "title"],
                "keyword" => "foo",
                "mode" => "natural"
            ]
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE MATCH (`content`, `title`) AGAINST ('foo' IN NATURAL LANGUAGE MODE)
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::where()
     * @covers ::field()
     * @dataProvider typesProvider
     */
    public function testRegularExpressionWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            'user_name[REGEXP]' => '[a-z0-9]*'
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE "user_name" REGEXP '[a-z0-9]*'
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @covers \Database\Driver::raw
     * @dataProvider typesProvider
     */
    public function testRawWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            'datetime' => Driver::raw('NOW()')
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE "datetime" = NOW()
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::where()
     * @covers ::field()
     * @dataProvider typesProvider
     */
    public function testLimitWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            'LIMIT' => 100
        ])->select();

        $this->assertQuery([
            'default' => <<<EOD
                SELECT "user_name"
                FROM "demo"
                LIMIT 100
                EOD,
        ], $this->database->driver()->queryString);
    }

    /**
     * @covers ::select()
     * @covers ::where()
     * @covers ::field()
     * @dataProvider typesProvider
     */
    public function testLimitOffsetWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            'LIMIT' => [20, 100]
        ])->select();

        $this->assertQuery([
            'default' => <<<EOD
                SELECT "user_name"
                FROM "demo"
                LIMIT 100 OFFSET 20
                EOD,
        ], $this->database->driver()->queryString);
    }

    /**
     * @covers ::select()
     * @covers ::where()
     * @covers ::field()
     * @dataProvider typesProvider
     */
    public function testGroupWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            'GROUP' => 'type',
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            GROUP BY "type"
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::where()
     * @covers ::field()
     * @dataProvider typesProvider
     */
    public function testGroupWithArrayWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            'GROUP' => [
                'type',
                'age',
                'gender'
            ]
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            GROUP BY "type","age","gender"
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @covers \Database\Driver::raw
     * @dataProvider typesProvider
     */
    public function testGroupWithRawWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            'GROUP' => Driver::raw("<location>, <gender>")
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            GROUP BY "location", "gender"
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::where()
     * @covers ::field()
     * @dataProvider typesProvider
     */
    public function testHavingWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            'HAVING' => [
                'user_id[>]' => 500
            ]
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            HAVING "user_id" > 500
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::where()
     * @covers ::field()
     * @covers \Database\Driver::raw
     * @dataProvider typesProvider
     */
    public function testHavingWithRawWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")->where([
            'HAVING' => Driver::raw('<location> = LOWER("NEW YORK")')
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            HAVING "location" = LOWER("NEW YORK")
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::field()
     * @covers ::where()
     * @covers \Database\Driver::raw
     * @dataProvider typesProvider
     */
    public function testHavingWithAggregateRawWhere($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field([
            "total" => Driver::raw('SUM(<salary>)')
        ])->where([
            'HAVING' => Driver::raw('SUM(<salary>) > 1000')
        ])->select();

        $this->assertQuery(
            <<<EOD
            SELECT SUM("salary") AS "total"
            FROM "demo"
            HAVING SUM("salary") > 1000
            EOD,
            $this->database->driver()->queryString
        );
    }

    /**
     * @covers ::select()
     * @covers ::where()
     * @covers ::field()
     * @covers \Database\Driver::raw
     * @dataProvider typesProvider
     */
    public function testRawWhereClause($type)
    {
        $this->database = $this->setType($type, true);

        $this->database->table($this->model->table())->field("user_name")
            ->where(Driver::raw("WHERE <id> => 10"))->select();

        $this->assertQuery(
            <<<EOD
            SELECT "user_name"
            FROM "demo"
            WHERE "id" => 10
            EOD,
            $this->database->driver()->queryString
        );
    }
}
