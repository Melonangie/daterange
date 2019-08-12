# ![Project Logo](./docs/1558602896738s.jpg)  Date Range Management - UI & API

## Introduction
The following code exercise was meant to address a coding challenge. The exercise was meant to showcase my understanding of php, without the use of a framework.

#### The challenge
Create a UI and a REST CRUD API where you will register the following:

REST
- Specify a price depending on a date range $date_start - $date_end.
- Date ranges can’t lead to changes in dates not belonged to its dates range.
- Modify other date ranges in order to apply the
 latest request.
- Date ranges without gaps should be merged.
- Date ranges are exclusive. 

UI
- Show all date ranges sorted by $date_start.
- Perform CRUD operations.
- Option to truncate tables in the DB.

## Naming conventions
Classes & Interfaces | Methods & Properties | Functions | Variables
------------ | -------------  | ------------- | -------------
UpperCamel  | lowerCamel | lowerCamel | lower_case

## CRUD operations
URI | Method | Action | Content Type | Description
------------ | ------------- | ------------- | ------------ | -------------
/daterange | GET | index | JSON |  Gets all date ranges records
/daterange/{date_start} | GET | show |  JSON |  Gets a single date range record
/daterange | POST | create | JSON |  Inserts a new date range record
/daterange | PUT | update | JSON |  Updates a particular date range record
/daterange/{date_start}  | DELETE | destroy | JSON |  Deletes a particular date range record
/daterange/all | DELETE | destroyall | JSON |  Truncates the date range DB table

### Query Builder API

The query builder API only works with a GET request. All dates need to have the format {YYYY-mm-dd}

Function | Variables | Description | Example
------------ | ------------- | ------------- | -------------
filter | {date_start:date}<br>{date_end:date}<br>{date_start:[date TO date]} | Filters the date ranges result set. For variable values: <ul><li>A wildcard {\*} is use to search for all the variable values.</li><li>The format {[timestamp TO *]} is use for a range query.</li></ul> | ?filter="date_start:2019-08-08"
offset | {int} | Specifies the starting point to returns from a result set. | ?offset=10
limit | {int} | Specifies the number of records to return from a result set. | ?limit=10
fields | {date_start}<br>{date_end}<br>{price} | Specifies the fields to return in a result set. | ?fields="date_start","price"
sort | {date_start order}<br>{date_end order}<br>{price order} | Specifies how to sort the result set. The value of order can be {asc} or {desc}. | ?sort="price desc"

####Examples:

Filter a single result:
```
/api/v1/daterange?filter="date_start:2019-08-08"
```

Filter a range of results:
```
/api/v1/daterange?filter="date_start:[2019-08-07 TO 2019-08-08]"
```

Get all results using pagination and sort order:
```
/api/v1/daterange?offset=10&limit=10&sort="price desc"
```

## Request
The POST, PUT and DELETE requests require sending a payload in JSON format. All dates need to have the format {YYYY-mm-dd}. The {date_start}, {date_end} and {price} parameters are required.
```javascript
{
    "date_start";: "2019-08-07",
    "date_end";: "2019-08-08",
    "price"; : 12.00
}
```

## Response
The response is a JSON array of date ranges.
```javascript
{
    "Code";: "200",
    "Message";: "Succesfully created a date range",
    "Details"; : null
}
```

Error messages are generated by the RestException. The Response has the following properties:

Property | Value
------------ | -------------
Code | An HTTP code. If not set, PHP Runtime sets it from the exception.
Message | Custom message for the end user.
Details | Details about an error, a message longer than “message” field. If not set, PHP Runtime sets it to the exception class name.

## Tests
TODO

## Dockers
There are 4 docker containers, one for NGINX, one for PHP, one for Redis, and
 one for MariaDB. 
 
They're orchestrated using a docker-compose file. Everything is under the
 /build directory.

### Start the environment
To start the environment run the ./start-docker.sh script. It'll create the database keys, and run docker-compose. The keys are created one directory above, but ideally they shouldn't be store in the same environmet, or handled by another service.

### Stop the environment
To stop the environment`` run the ./stop-docker.sh script. It'll stop and remove the containers, run system prune, remove the volumes, and database keys.
