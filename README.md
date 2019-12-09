# bulk-insert-query
Bulk Insert Query with Doctrine\DBAL

```
$builder = new BulkInsertQuery($em, $tableName);
$builder->setColumns($listTableColumns);

$builder->setValues($values);
$builder->execute();
```
