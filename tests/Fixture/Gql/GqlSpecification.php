<?php

declare(strict_types=1);

namespace Tests\Fixture\Gql;

use Generator;

/**
 * Every query, expression and pattern the GQL documentation prints, transcribed.
 *
 * peq's query language is worth having only if it is GQL and not a dialect that looks
 * like it, and that is a claim about text: the things the specification prints must
 * be things peq reads. So they are all here, copied from the pages they appear on,
 * keyed by the section that prints them — and a contract test runs every one of them
 * through peq's reader.
 *
 * What is left out is left out for a stated reason. Grammar templates like
 * `MATCH <graph pattern>` are notation about the language rather than sentences in
 * it; the examples the guide marks with a cross are examples of what GQL rejects; and
 * the fragments that are bare patterns or bare expressions are here as patterns and
 * expressions rather than as queries, because that is what they are.
 *
 * @see https://learn.microsoft.com/fabric/graph/gql-language-guide GQL language guide
 * @see https://learn.microsoft.com/fabric/graph/gql-expressions GQL expressions, predicates, and functions
 */
final class GqlSpecification
{
    /**
     * Every complete query the documentation prints.
     *
     * @return Generator<string, string> The query, by the section that prints it
     */
    public static function queries(): Generator
    {
        yield 'language guide, what makes gql special' => <<<'GQL'
            MATCH (person:Person)-[:knows]-(friend:Person)
            WHERE person.birthday < 19990101 
              AND friend.birthday < 19990101
            RETURN person.firstName || ' ' || person.lastName AS person_name, 
                   friend.firstName || ' ' || friend.lastName AS friend_name
            GQL;

        yield 'language guide, start simple: find all people' => <<<'GQL'
            MATCH (p:Person)
            RETURN p.firstName, p.lastName
            GQL;

        yield 'language guide, add filtering: find specific people' => <<<'GQL'
            MATCH (p:Person)
            FILTER p.firstName = 'Alice'
            RETURN p.firstName, p.lastName, p.birthday
            GQL;

        yield 'language guide, basic query structure' => <<<'GQL'
            MATCH (n:Person)-[:knows]-(m:Person)
            FILTER n.birthday = m.birthday
            RETURN count(*) AS same_age_friends
            GQL;

        yield 'language guide, example of statement composition' => <<<'GQL'
            -- Data flows: Match → Let → Filter → Order → Limit → Return
            MATCH (p:Person)-[:workAt]->(c:Company)           -- Input: unit table, Output: (p, c) table
            LET fullName = p.firstName || ' ' || p.lastName   -- Input: (p, c) table, Output: (p, c, fullName) table
            FILTER c.name CONTAINS 'Air'                      -- Input: (p, c, fullName) table, Output: filtered table
            ORDER BY fullName                                 -- Input: filtered table, Output: sorted table
            LIMIT 10                                          -- Input: sorted table, Output: top 10 rows table
            RETURN fullName, c.name AS companyName            -- Input: top 10 rows table
                                                              -- Output: projected (fullName, companyName) result table
            GQL;

        yield 'language guide, graph patterns: finding structure' => <<<'GQL'
            -- Find paths without visiting the same :knows edge twice
            MATCH TRAIL (src:Person)-[:knows]->{1,4}(dst:Person)
            WHERE src.firstName = 'Alice' AND dst.firstName = 'Bob'
            RETURN count(*) AS num_connections
            GQL;

        yield 'language guide, graph patterns: finding structure (2)' => <<<'GQL'
            -- Find acyclic paths in social networks
            MATCH TRAIL (p:Person)-[e:knows]->{,3}(celebrity:Person)
            RETURN 
              p.firstName || ' ' || p.lastName AS person_name, 
              celebrity.firstName || ' ' || celebrity.lastName AS celebrity_name, 
              count(e) AS distance
            LIMIT 1000
            GQL;

        yield 'language guide, graph patterns: finding structure (3)' => <<<'GQL'
            -- Find paths where no person appears more than once
            MATCH ACYCLIC (src:Person)-[:knows]->{1,4}(dst:Person)
            WHERE src.firstName = 'Alice'
            RETURN dst.firstName, dst.lastName
            LIMIT 100
            GQL;

        yield 'language guide, graph patterns: finding structure (4)' => <<<'GQL'
            -- Edge variable 'e' binds to a single edge for each result row
            MATCH (p:Person)-[e:knows]->(friend:Person)
            RETURN p.firstName, e.creationDate, friend.firstName  -- e refers to one specific relationship
            LIMIT 1000
            
            -- Edge variable 'e' binds to a group list of all edges in the path
            MATCH (p:Person)-[e:knows]->{2,4}(friend:Person)  
            RETURN 
              p.firstName || ' ' || p.lastName AS person_name, 
              friend.firstName || ' ' || friend.lastName AS friend_name, 
              -- e is a list
              size(e) AS num_edges
            LIMIT 1000
            GQL;

        yield 'language guide, graph patterns: finding structure (5)' => <<<'GQL'
            MATCH (p:Person), (p)-[:workAt]->(c:Company), (p)-[:isLocatedIn]->(city:City)
            RETURN p.firstName, p.lastName, c.name AS company_name, city.name AS city_name
            LIMIT 1000
            GQL;

        yield 'language guide, match statement' => <<<'GQL'
            -- Input: unit table (no columns, one row)
            -- Pattern variables: p, c  
            -- Output: table with (p, c) columns for each person-company match
            MATCH (p:Person)-[:workAt]->(c:Company)
            GQL;

        yield 'language guide, match statement (2)' => <<<'GQL'
            -- Filter pattern matches
            MATCH (p:Person)-[:workAt]->(c:Company) WHERE p.lastName = c.name
            GQL;

        yield 'language guide, match statement (3)' => <<<'GQL'
            -- Shared variable 'p' joins the two patterns
            -- Output: people with both workplace and residence data
            MATCH (p:Person)-[:workAt]->(c:Company), 
                  (p)-[:isLocatedIn]->(city:City)
            GQL;

        yield 'language guide, optional match statement' => <<<'GQL'
            -- Find all people and, if available, their workplace
            MATCH (p:Person)
            OPTIONAL MATCH (p)-[:workAt]->(c:Company)
            RETURN p.firstName, p.lastName, c.name AS company_name
            GQL;

        yield 'language guide, let statement' => <<<'GQL'
            MATCH (p:Person)
            LET fullName = p.firstName || ' ' || p.lastName
            RETURN *
            LIMIT 1000
            GQL;

        yield 'language guide, let statement (2)' => <<<'GQL'
            MATCH (p:Person)
            LET adjustedAge = 2000 - (p.birthday / 10000),
                fullProfile = p.firstName || ' ' || p.lastName || ' (' || p.gender || ')'
            RETURN *
            LIMIT 1000
            GQL;

        yield 'language guide, filter statement' => <<<'GQL'
            MATCH (p:Person)
            FILTER p.birthday < 19980101 AND p.gender = 'female'
            RETURN *
            GQL;

        yield 'language guide, filter statement (2)' => <<<'GQL'
            MATCH (p:Person)
            FILTER (p.gender = 'male' AND p.birthday < 19940101) 
              OR (p.gender = 'female' AND p.birthday < 19990101)
              OR p.browserUsed = 'Edge'
            RETURN *
            GQL;

        yield 'language guide, order by statement' => <<<'GQL'
            MATCH (p:Person)
            RETURN *
            ORDER BY p.firstName DESC,               -- Primary: by first name (Z-A)
                     p.birthday ASC,                 -- Secondary: by age (oldest first)
                     p.id DESC                       -- Tertiary: by ID (highest first)
            GQL;

        yield 'language guide, order by statement (2)' => <<<'GQL'
            MATCH (a:Person)-[r:knows]->(b:Person)
            LET aName = a.firstName || ' ' || a.lastName
            LET bName = b.firstName || ' ' || b.lastName
            ORDER BY r.creationDate DESC
            /* intermediary result _IS_ guaranteed to be ordered here */
            RETURN aName, bName, r.creationDate AS since
            /* final result _IS_ _NOT_ guaranteed to be ordered here  */
            GQL;

        yield 'language guide, order by statement (3)' => <<<'GQL'
            MATCH (a:Person)-[r:knows]->(b:Person)
            LET aName = a.firstName || ' ' || a.lastName
            LET bName = b.firstName || ' ' || b.lastName
            /* intermediary result _IS_ _NOT_ guaranteed to be ordered here */
            RETURN aName, bName, r.creationDate AS since
            ORDER BY r.creationDate DESC
            /* final result _IS_ guaranteed to be ordered here              */
            GQL;

        yield 'language guide, offset and limit statements' => <<<'GQL'
            -- Basic top-N query
            MATCH (p:Person)
            RETURN *
            ORDER BY p.id DESC
            LIMIT 10                                 -- Top 10 by ID
            GQL;

        yield 'language guide, return: basic result projection' => <<<'GQL'
            MATCH (p:Person)-[:workAt]->(c:Company)
            RETURN p.firstName || ' ' || p.lastName AS name, 
                   p.birthday, 
                   c.name
            GQL;

        yield 'language guide, return: basic result projection (2)' => <<<'GQL'
            MATCH (p:Person)-[:workAt]->(c:Company)
            RETURN p.firstName AS first_name, 
                   p.lastName AS last_name,
                   c.name AS company_name
            GQL;

        yield 'language guide, return: basic result projection (3)' => <<<'GQL'
            MATCH (p:Person)-[:workAt]->(c:Company)
            RETURN p.firstName || ' ' || p.lastName AS name, 
                   p.birthday AS birth_year, 
                   c.name AS company
            ORDER BY birth_year ASC
            LIMIT 10
            GQL;

        yield 'language guide, return: basic result projection (4)' => <<<'GQL'
            -- Remove duplicate combinations
            MATCH (p:Person)-[:workAt]->(c:Company)
            RETURN DISTINCT p.gender, p.browserUsed, p.birthday AS birth_year
            ORDER BY p.gender, p.browserUsed, birth_year
            GQL;

        yield 'language guide, return: basic result projection (5)' => <<<'GQL'
            MATCH (p:Person)-[:workAt]->(c:Company)
            RETURN count(DISTINCT p) AS employee_count
            GQL;

        yield 'language guide, return with group by: grouped result projection' => <<<'GQL'
            MATCH (p:Person)-[:workAt]->(c:Company)
            LET companyName = c.name
            RETURN companyName, 
                   count(*) AS employeeCount,
                   avg(p.birthday) AS avg_birth_year
            GROUP BY companyName
            ORDER BY employeeCount DESC
            GQL;

        yield 'language guide, return with group by: grouped result projection (2)' => <<<'GQL'
            MATCH (p:Person)
            LET gender = p.gender
            LET browser = p.browserUsed
            RETURN gender,
                   browser,
                   count(*) AS person_count,
                   avg(p.birthday) AS avg_birth_year,
                   min(p.creationDate) AS first_joined,
                   max(p.id) AS highest_id
            GROUP BY gender, browser
            ORDER BY avg_birth_year DESC
            LIMIT 10
            GQL;

        yield 'language guide, union all' => <<<'GQL'
            -- Combine results from two separate pattern matches
            MATCH (p:Person)-[:workAt]->(c:Company)
            RETURN p.firstName AS name, c.name AS affiliation
            UNION ALL
            MATCH (p:Person)-[:studyAt]->(u:University)
            RETURN p.firstName AS name, u.name AS affiliation
            GQL;

        yield 'language guide, multistep pattern progression' => <<<'GQL'
            -- Build complex analysis step by step
            MATCH (company:Company)<-[:workAt]-(employee:Person)
            LET companyName = company.name
            MATCH (employee)-[:isLocatedIn]->(city:City)
            FILTER employee.birthday < 19850101
            LET cityName = city.name
            RETURN companyName, cityName, avg(employee.birthday) AS avgBirthday, count(employee) AS employeeCount
            GROUP BY companyName, cityName
            ORDER BY avgBirthday DESC
            GQL;

        yield 'language guide, use of horizontal aggregation' => <<<'GQL'
            -- Find people and their minimum distance to people working at Microsoft
            MATCH TRAIL (p:Person)-[e:knows]->{,5}(:Person)-[:workAt]->(:Company { name: 'Microsoft'})
            LET p_name = p.lastName || ', ' || p.firstName
            RETURN p_name, min(count(e)) AS minDistance 
            GROUP BY p_name
            ORDER BY minDistance DESC
            GQL;

        yield 'language guide, variable binding and scoping patterns' => <<<'GQL'
            -- Variables flow forward through subsequent statements 
            MATCH (p:Person)                                    -- Bind p 
            LET fullName = p.firstName || ' ' || p.lastName     -- Bind concatenation of p.firstName and p.lastName as fullNume
            FILTER fullName CONTAINS 'Smith'                    -- Filter for fullNames with “Smith” substring (p is still bound)
            RETURN p.id, fullName                               -- Only return p.id and fullName (p is dropped from scope)
            GQL;

        yield 'language guide, variable reuse for joins across statements' => <<<'GQL'
            -- Multi-statement joins using variable reuse
            MATCH (p:Person)-[:workAt]->(:Company)          -- Find people with jobs
            MATCH (p)-[:isLocatedIn]->(:City)               -- Same p: people with both job and residence
            MATCH (p)-[:knows]->(friend:Person)             -- Same p: their social connections
            RETURN *
            GQL;

        yield 'language guide, variable visibility in complex queries' => <<<'GQL'
            -- Variables remain visible until overridden or query ends
            MATCH (p:Person)                     -- p available from here
            LET gender = p.gender                -- gender available from here  
            MATCH (p)-[:knows]->(e:Person)       -- p still refers to original person
                                                 -- e is new variable for managed employee
            RETURN p.firstName AS manager, e.firstName AS friend, gender
            GQL;

        yield 'language guide, vertical aggregation with group by' => <<<'GQL'
            MATCH (p:Person)-[:workAt]->(c:Company)
            RETURN c.name AS companyName, 
                   count(*) AS employee_count, 
                   avg(p.birthday) AS avg_birth_year
            GROUP BY companyName
            GQL;

        yield 'language guide, horizontal aggregation with group list variables' => <<<'GQL'
            -- Group list variable 'edges' enables horizontal aggregation
            MATCH (p:Person)-[edges:knows]->{2,4}(friend:Person)
            RETURN p.firstName || ' ' || p.lastName AS person_name, 
                   friend.firstName || ' ' || friend.lastName AS friend_name,
                   size(edges) AS degrees_of_separation,
                   avg(edges.creationDate) AS avg_connection_age,
                   min(edges.creationDate) AS oldest_connection
            GQL;

        yield 'language guide, variable-length edge binding contexts' => <<<'GQL'
            -- Edge variable 'e' refers to each individual edge during filtering
            MATCH (p:Person)-[e:knows WHERE e.creationDate > zoned_datetime('2000-01-01T00:00:00Z')]->{2,4}(friend:Person)
            -- 'e' is evaluated for each edge in the path during matching
            RETURN *
            GQL;

        yield 'language guide, variable-length edge binding contexts (2)' => <<<'GQL'
            -- Edge variable 'edges' becomes a list of all qualifying edges
            MATCH (p:Person)-[e:knows]->{2,4}(friend:Person)
            RETURN size(e) AS num_edges,                    -- Number of edges in path
                   e[0].creationDate AS first_edge,         -- First edge in path
                   avg(e.creationDate) AS avg_age           -- Horizontal aggregation
            GQL;

        yield 'language guide, combining vertical and horizontal aggregation' => <<<'GQL'
            -- Find average connection age by city pairs
            MATCH (p1:Person)-[:isLocatedIn]->(c1:City)
            MATCH (p2:Person)-[:isLocatedIn]->(c2:City)
            MATCH (p1)-[e:knows]->{1,3}(p2)
            RETURN c1.name AS city1,
                   c2.name AS city2,
                   count(*) AS connection_paths,                  -- Vertical: count paths per city pair
                   avg(size(e)) AS avg_degrees,                   -- Horizontal then vertical: path lengths
                   avg(avg(e.creationDate)) AS avg_connection_age -- Horizontal then vertical: connection ages
            GROUP BY city1, city2
            GQL;

        yield 'language guide, error handling strategies' => <<<'GQL'
            MATCH (p:Person)
            -- Use COALESCE for missing properties
            LET displayName = coalesce(p.firstName, p.lastName, 'Unknown')
            LET contact = coalesce(p.locationIP, p.browserUsed, 'No info')
            RETURN *
            GQL;

        yield 'language guide, error handling strategies (2)' => <<<'GQL'
            MATCH (p:Person)
            -- Be explicit about null handling
            FILTER p.id IS NOT NULL AND p.id > 0
            -- Instead of just: FILTER p.id > 0
            RETURN *
            GQL;

        yield 'expressions reference, comparison predicates' => <<<'GQL'
            MATCH (p:Person)
            FILTER WHERE p.birthday <= 20050915
            RETURN p.firstName
            GQL;

        yield 'expressions reference, logical expressions' => <<<'GQL'
            MATCH (p:Person)
            FILTER WHERE p.birthday <= 20050915 AND p.firstName = 'John'
            RETURN p.firstName || ' ' || p.lastName AS fullName
            GQL;

        yield 'expressions reference, arithmetic expressions' => <<<'GQL'
            MATCH (p:Person)
            LET birth_year = p.birthday / 10000
            RETURN birth_year
            GQL;

        yield 'expressions reference, aggregate functions over a whole table' => <<<'GQL'
            MATCH (p:Person)
            RETURN count(*) AS total_people, avg(p.birthday) AS average_birth_year
            GQL;

        yield 'expressions reference, aggregate functions with grouping' => <<<'GQL'
            MATCH (p:Person)-[:isLocatedIn]->(c:City)
            RETURN c.name, count(*) AS population, avg(p.birthday) AS average_birth_year
            GROUP BY c.name
            GQL;

        yield 'expressions reference, horizontal aggregate functions' => <<<'GQL'
            MATCH (p:Person)-[edges:knows]->{1,3}(:Person)
            RETURN p.firstName, avg(edges.creationDate) AS avg_connection_date
            GQL;

        yield 'expressions reference, conditional expressions' => <<<'GQL'
            MATCH (p:Person)
            RETURN p.firstName,
                   CASE
                     WHEN p.gender = 'male' THEN 'M'
                     WHEN p.gender = 'female' THEN 'F'
                     ELSE 'Other'
                   END AS gender_code,
                   NULLIF(p.browserUsed, 'Unknown') AS browser
            GQL;

        yield 'expressions reference, string functions' => <<<'GQL'
            MATCH (p:Person)
            WHERE char_length(p.firstName) > 5
            RETURN upper(p.firstName) AS name_upper
            GQL;

        yield 'expressions reference, graph functions' => <<<'GQL'
            MATCH p=(:Company)<-[:workAt]-(:Person)-[:knows]-{1,3}(:Person)-[:workAt]->(:Company)
            RETURN nodes(p) AS chain_of_colleagues, path_length(p) AS hops
            GQL;

        yield 'expressions reference, list functions' => <<<'GQL'
            MATCH (p:Person)-[:hasInterest]->(t:Tag)
            WHERE size(collect_list(t)) > 3
            RETURN p.firstName, collect_list(t.name) AS interests
            GQL;

        yield 'expressions reference, temporal functions' => 'RETURN zoned_datetime() AS now';

        yield 'expressions reference, generic functions' => <<<'GQL'
            MATCH (p:Person)
            RETURN coalesce(p.firstName, 'Unknown') AS display_name,
                   to_json_string(p) AS person_json
            GQL;
    }

    /**
     * Every expression and predicate the documentation prints on its own.
     *
     * @return Generator<string, string> The expression, by what the documentation calls it
     */
    public static function expressions(): Generator
    {
        yield 'a whole number literal' => '42';

        yield 'an approximate number literal with a suffix' => '1.0d';

        yield 'a truth literal' => 'TRUE';

        yield 'a string literal in double quotes' => '"Hello, graph!"';

        yield 'a string literal in single quotes' => "'Alice'";

        yield 'a list literal' => '[ 1, 2, 3 ]';

        yield 'the absence of a value' => 'NULL';

        yield 'a moment written out' => "ZONED_DATETIME('2024-01-15T10:30:00Z')";

        yield 'a moment now' => 'zoned_datetime()';

        yield 'equality against nothing' => '5 = NULL';

        yield 'nothing against nothing' => 'NULL = NULL';

        yield 'a property that is there' => 'p.locationIP IS NOT NULL';

        yield 'a property that is not' => 'p.browserUsed IS NULL';

        yield 'membership' => "p.firstName IN ['Alice', 'Bob', 'Charlie']";

        yield 'refused membership' => "p.gender NOT IN ['male', 'female']";

        yield 'a substring' => "p.firstName CONTAINS 'John'";

        yield 'a prefix' => "p.browserUsed STARTS WITH 'Chrome'";

        yield 'a suffix' => "p.locationIP ENDS WITH '.1'";

        yield 'string concatenation' => "p.firstName || ' ' || p.lastName";

        yield 'a comparison' => 'p.birthday < 19980101';

        yield 'a count over every row' => 'count(*)';

        yield 'an exclusive disjunction' => 'a XOR b';

        yield 'a parenthesised disjunction' => "(p.birthday < 20050915 OR p.birthday > 19651231) AND p.gender = 'male'";

        yield 'a predicate with explicit grouping' => "p.gender = 'female' AND (p.firstName STARTS WITH 'A' OR p.id > 1000)";

        yield 'property access' => 'p.firstName';

        yield 'property access on an edge' => 'edge.creationDate';

        yield 'the first element of a list' => 'interests[0]';

        yield 'the second element of a list' => 'interests[1]';

        yield 'a searched choice' => 'CASE WHEN condition1 THEN result1 WHEN condition2 THEN result2 ELSE default_result END';

        yield 'a simple choice' => 'CASE expression WHEN value1 THEN result1 WHEN value2 THEN result2 ELSE default_result END';

        yield 'withdrawing a value' => 'NULLIF(a, b)';

        yield 'the first value that is there' => "coalesce(p.nickname, p.firstName, '???')";

        yield 'a value as JSON' => 'to_json_string(value)';

        yield 'the size of a list' => 'size(list)';

        yield 'a list cut to a length' => 'trim(list, 3)';

        yield 'the labels of an element' => 'labels(node_or_edge)';

        yield 'the symbols of a path' => 'nodes(path)';

        yield 'the relations of a path' => 'edges(path)';

        yield 'everything a path is made of' => 'elements(path)';

        yield 'the length of a path' => 'path_length(path)';

        yield 'the length of a string' => 'char_length(string)';

        yield 'a string in upper case' => "upper(p.firstName) = 'ALICE'";

        yield 'a string in lower case' => 'lower(string)';

        yield 'a string without its whitespace' => 'trim(string)';

        yield 'a list written out' => 'string_join(list, separator)';

        yield 'a whole number division' => 'p.birthday / 10000';
    }

    /**
     * Every graph pattern the documentation prints on its own.
     *
     * @return Generator<string, string> The pattern, by what the documentation calls it
     */
    public static function patterns(): Generator
    {
        yield 'a relation' => '(p:Person)-[:knows]->(f:Person)';

        yield 'a relation to a company' => '(p:Person)-[:workAt]->(c:Company)';

        yield 'a relation between places' => '(ci:City)-[:isPartOf]->(co:Country)';

        yield 'a relation narrowed after the pattern' => "(p:Person)-[:workAt]->(c:Company)\nWHERE p.firstName = 'Alice'";

        yield 'either of two labels' => '(:Person|Company)-[:isLocatedIn]->(p:City|Country)';

        yield 'both of two labels' => '(:Place&City)';

        yield 'a refused label' => '(:Person&!Company)';

        yield 'a name written twice' => '(c:Company)<-[:workAt]-(x:Person)-[:knows]-(y:Person)-[:workAt]->(c)';

        yield 'a predicate inside a node pattern' => '(p:Person WHERE p.birthday < 19940101)-[:workAt]->(c:Company WHERE c.id > 1000)';

        yield 'a predicate inside an edge pattern' => '(p:Person)-[w:workAt WHERE w.workFrom >= 2000]->(c:Company)';

        yield 'a bounded repetition' => '(:Person)-[:knows]->{1,3}(:Person)';

        yield 'a repetition with no lower bound' => '(p:Person)-[e:knows]->{,3}(celebrity:Person)';

        yield 'properties written in the pattern' => "(:Company { name: 'Microsoft'})";
    }

    /**
     * The statements GQL defines that peq answers questions with.
     *
     * @return list<string> The keyword each one begins with
     */
    public static function statementsRead(): array
    {
        return ['MATCH', 'OPTIONAL', 'LET', 'FILTER', 'ORDER', 'OFFSET', 'SKIP', 'LIMIT', 'RETURN'];
    }

    /**
     * The statements GQL defines that peq refuses, and what each one would do.
     *
     * peq answers questions about source code it has just read. A statement that
     * changes a graph, manages a session or declares a type would be describing a
     * database that does not exist, so each is refused — and refused by name, so a
     * reader is told the statement is not supported here rather than told their
     * perfectly good GQL is a syntax error.
     *
     * @return array<string, string> What the statement does, by the keyword it begins with
     */
    public static function statementsRefused(): array
    {
        return [
            'INSERT' => 'adds nodes and edges to a graph',
            'SET' => 'assigns properties or labels',
            'REMOVE' => 'takes properties or labels away',
            'DELETE' => 'removes nodes and edges from a graph',
            'DETACH' => 'removes a node together with its edges',
            'NODETACH' => 'removes a node only when it has no edges',
            'CALL' => 'invokes a procedure',
            'USE' => 'chooses the graph the statements after it run against',
            'CREATE' => 'declares a graph, a graph type or a schema',
            'DROP' => 'discards a graph, a graph type or a schema',
            'SESSION' => 'sets or resets a session parameter',
            'START' => 'begins a transaction',
            'COMMIT' => 'ends a transaction, keeping what it did',
            'ROLLBACK' => 'ends a transaction, undoing what it did',
            'FINISH' => 'ends a query without a result table',
            'NEXT' => 'chains one query onto the result of another',
            'YIELD' => 'names the columns a called procedure returns',
        ];
    }
}
