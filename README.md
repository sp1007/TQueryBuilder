# TQueryBuilder
SQL query builder for PDO in php

This is tiny php class to build sql query for PDO. The class supports SELECT, INSERT, UPDATE, DELETE with WHERE, HAVING, GROUP BY, ORDER BY, LIMIT OFFSET, JOIN ON and subquery.

#Use TQueryBuilder with PDO:

      $queryBuilder = TQueryBuilder()::newQuery();
      //or $queryBuilder = new TQueryBuilder();

      //creation of query will be presented below.

      try {
            $db = new PDO($dsn, $user, $password);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      
            $stmt = $db->prepare($queryBuilder->getSQL()); //prepare sql
      
            $stmt->execute($queryBuilder->getBindingValues()); //excute sql with binding values
      
      } catch (PDOException $e) {
            //exception
      }

#TQueryBuilder Examples:

sql: SELECT EmployeeID, FirstName, LastName, City, Country AS cty FROM employees WHERE row(City, Country) IN (SELECT City, Country FROM customers);

      $sql = TQueryBuilder::newQuery()
      ->select(['EmployeeID', 'FirstName', 'LastName', 'City', 'cty' => 'Country'])
      ->from('employees')
      ->where([
            'row(City, Country)', 
            TQueryBuilder::newQuery()->select(['City', 'Country'])->from('customers')]
      , 'IN');

=================================    

sql: SELECT DISTINCT a.ProductID, a.UnitPrice AS Max_unit_price_sold FROM order_details AS a INNER JOIN (SELECT ProductID, max(UnitPrice) AS Max_unit_price_sold FROM order_details GROUP BY ProductID) AS b ON a.ProductID = b.ProductID AND a.UnitPrice = b.Max_unit_price_sold ORDER BY a.ProductID

      $sql = TQueryBuilder::newQuery()
            ->select(['a.ProductID', 'Max_unit_price_sold' => 'a.UnitPrice'], true)
            ->from('order_details', 'a')
            ->join(
                  TQueryBuilder::newQuery()
                  ->select(['ProductID', 'Max_unit_price_sold' => 'max(UnitPrice)'])
                  ->from('order_details')
                  ->group_by('ProductID')
            , 'b', 'INNER')
            ->on(['a.ProductID', 'b.ProductID'])->and_(['a.UnitPrice','b.Max_unit_price_sold'])
            ->order_by('a.ProductID');

=================================

sql: SELECT EmployeeID, FirstName, LastName, City, Country AS cty FROM employees WHERE row(City, Country) = 100

      $sql = TQueryBuilder::newQuery()
            ->select(['EmployeeID', 'FirstName', 'LastName', 'City', 'cty' => 'Country'])
            ->from('employees')
    -       >where(['row(City, Country)', 'value' => 100]);
    
result of builder:

      $sql->getSQL():  SELECT EmployeeID, FirstName, LastName, City, Country AS cty FROM employees WHERE row(City, Country) = :value1

      $sql->getBindingValues():  array(1) { [":value1"]=> int(100) } 
    
=================================

sql: INSERT INTO suppliers (supplier_id, supplier_name) VALUES (24553, 'IBM');

      $sql = TQueryBuilder::newQuery()->insert('suppliers', ['supplier_id' => 24553, 'supplier_name' =>'IBM']);

result of builder:

      $sql->getSQL():  INSERT INTO suppliers(supplier_id, supplier_name) VALUES (:insert1, :insert2)

      $sql->getBindingValues():  array(2) { [":insert1"]=> int(24553) [":insert2"]=> string(3) "IBM" }

=================================

sql: INSERT INTO suppliers (supplier_id, supplier_name) SELECT account_no, name FROM customers WHERE city = 'Newark';

      $sql = TQueryBuilder::newQuery()
            ->insert('suppliers', ['supplier_id', 'supplier_name'], true)
            ->select(['account_no', 'name'])
            ->from('customers')
            ->where(['city', 'value' => 'Newark']);
        
result of builder:

      $sql->getSQL():  INSERT INTO suppliers(supplier_id, supplier_name) SELECT account_no, name FROM customers WHERE city = :value1

      $sql->getBindingValues():  array(1) { [":value1"]=> string(6) "Newark" }        

=================================

sql: INSERT INTO clients (client_id, client_name, client_type) SELECT supplier_id, supplier_name, 'advertising' FROM suppliers WHERE NOT EXISTS (SELECT * FROM clients WHERE clients.client_id = suppliers.supplier_id);

      $sql = TQueryBuilder::newQuery()
            ->insert('clients', ['client_id', 'client_name', 'client_type'], true)
            ->select(['supplier_id', 'supplier_name', '\'advertising\''])
            ->from('suppliers')
            ->where([
                  'NOT EXISTS',
                  TQueryBuilder::newQuery()
                  ->select()
                  ->from('clients')
                  ->where(['clients.client_id', 'suppliers.supplier_id'])
            ], '');

=================================  

sql: UPDATE suppliers SET supplier_id = 150, supplier_name = 'Apple', city = 'Cupertino' WHERE supplier_name = 'Google';

      $sql = TQueryBuilder::newQuery()
            ->update('suppliers', ['supplier_id' => 150, 'supplier_name' => 'Apple', 'city' => 'Cupertino'])
            ->where(['supplier_name', 'value' => 'Google']);
    
=================================  

sql: UPDATE summary_data SET current_category = (SELECT category_id FROM products WHERE products.product_id = summary_data.product_id) WHERE EXISTS (SELECT category_id FROM products WHERE products.product_id BETWEEN 50 AND 200)

      $sql = TQueryBuilder::newQuery()
            ->update('summary_data',[
                  'current_category' => TQueryBuilder::newQuery()
                                          ->select('category_id')
                                          ->from('products')
                                          ->where(['products.product_id', 'summary_data.product_id'])
            ])
            ->where([
                  'EXISTS',
                  TQueryBuilder::newQuery()
                  ->select('category_id')
                  ->from('products')
                  ->where(['products.product_id', 'value' => '50'], 'BETWEEN')->and_(['value' => '200'])
            ],'');

=================================

with delete query

sql: DELETE FROM table_name WHERE id = 10 AND value >= 50

      $sql = TQueryBuilder::newQuery()
            ->delete('table_name')
            ->where(['id', 'value'=>10])
            ->and_(['value', 'value' => 50], '>=');
