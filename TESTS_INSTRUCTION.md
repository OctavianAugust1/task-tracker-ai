A guide to writing tests on PHPUnit 10.5

In Laravel 9, you should only write Unit and Feature tests.
For Unit tests, inherit from the PHPUnit\Framework\TestCase class. For Feature tests, inherit from the Tests\TestCase class.

What are Unit tests?
A Unit test (also known as a module test) is a way to test a minimal piece of code (usually a single method in a class) in complete isolation from the rest of the system.
In the context of Laravel and the layered architecture, "isolation" means that your test:
- Does not connect to a real or test database.
- Does not make real HTTP requests to other microservices.
- Does not load the entire Laravel framework (if possible), so that the tests can be executed in milliseconds.
All external dependencies of the class (such as classes for working with databases or APIs) are replaced with mocks or stubs, which are special mock objects.

Unit tests in a layered architecture
In a typical layered architecture (Controller -> Service -> Repository), the testing strategy looks like this:
Controllers (Controllers): They are usually covered by Feature tests (integration tests). We hit an endpoint and verify the JSON response.
Repositories (Repositories): It is also better to cover them with Integration tests using a test database (SQLite or PostgreSQL/MySQL transactions), as their main purpose is to work with SQL.
Services (Services): This is the perfect place for Unit tests. This is where your business logic lives. When testing a service, we mock repositories and other services.

Feature tests may test a larger portion of your code, including how several objects interact with each other or even a full HTTP request to a JSON endpoint.

For tests, there are these commands from the Laravel 9 framework.:
php artisan test - Run the application tests
php artisan make:test - Create a new test class

When writing tests, use the AAA (Arrange-Act-Assert) approach
Arrange: Set up the test environment.
Act: Execute the code to test.
Assert: Verify the results.

For each test, write a description in PHPDocBlock about what this test checks in Russian. Declare common dependencies in the setUp method, and set them in the class properties.

Use the Mockery library for mocks.
Using the infection --threads=12 command, you can run mutation tests of the infection/infection library. You can read more about this library here. https://infection.github.io/guide/index.html

Don't use "Reflection" in your tests.
Don't use "file_get_contents" in your tests to check the contents of classes.
Declare common dependencies in the setUp method and set them in the class properties.
After writing the tests, run them and fix them if errors occur, and so on until they disappear.

For Feature tests, always use the "DatabaseTransactions" trait and call "Http::preventStrayRequests()" in the "setUp" method. Do not mock services or repositories, as you need to test the full API path from start to finish. Only mock requests to external services.
In Unit tests, if you create private methods that create a mocked model, then after calling this method in the test, use a DocBlock like "/** @var MockInterface|<CurrentModel> $model */" to assign the result to a variable.
For Unit tests, use "CoversClass" with the class you are testing (usually "Service" or "Job", but not a controller or repository). For Feature tests, use "CoversClass" for controller and repository classes (if you need to specify a repository, use the context provided by the controller; you do not need to specify additional repositories, especially if they are mostly related to other controllers).
Try not to include any code in the description of feature tests. Instead, explain them in simple terms.
