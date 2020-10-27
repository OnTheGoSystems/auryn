<?php

namespace WPML\Auryn\Test;

use \WPML\Auryn\Injector;
use \WPML\Auryn\InjectionException;
use PHPUnit\Framework\TestCase;

class InjectorTest extends TestCase
{
    public function testMakeInstanceInjectsSimpleConcreteDependency()
    {
        $injector = new Injector;
        $this->assertEquals(new TestNeedsDep(new TestDependency),
            $injector->make( 'WPML\Auryn\Test\TestNeedsDep')
        );
    }

    public function testMakeInstanceReturnsNewInstanceIfClassHasNoConstructor()
    {
        $injector = new Injector;
        $this->assertEquals(new TestNoConstructor, $injector->make( 'WPML\Auryn\Test\TestNoConstructor'));
    }

    public function testMakeInstanceReturnsAliasInstanceOnNonConcreteTypehint()
    {
        $injector = new Injector;
        $injector->alias( 'WPML\Auryn\Test\DepInterface',  'WPML\Auryn\Test\DepImplementation');
        $this->assertEquals(new DepImplementation, $injector->make( 'WPML\Auryn\Test\DepInterface'));
    }

    /**
     * @expectedException \WPML\Auryn\InjectionException
     * @expectedExceptionMessage Injection definition required for interface WPML\Auryn\Test\DepInterface
     * @expectedExceptionCode \WPML\Auryn\Injector::E_NEEDS_DEFINITION
     */
    public function testMakeInstanceThrowsExceptionOnInterfaceWithoutAlias()
    {
        $injector = new Injector;
        $injector->make( 'WPML\Auryn\Test\DepInterface');
    }

    /**
     * @expectedException \WPML\Auryn\InjectionException
     * @expectedExceptionMessage Injection definition required for interface WPML\Auryn\Test\DepInterface
     * @expectedExceptionCode \WPML\Auryn\Injector::E_NEEDS_DEFINITION
     */
    public function testMakeInstanceThrowsExceptionOnNonConcreteCtorParamWithoutImplementation()
    {
        $injector = new Injector;
        $injector->make( 'WPML\Auryn\Test\RequiresInterface');
    }

    public function testMakeInstanceBuildsNonConcreteCtorParamWithAlias()
    {
        $injector = new Injector;
        $injector->alias( 'WPML\Auryn\Test\DepInterface',  'WPML\Auryn\Test\DepImplementation');
        $obj = $injector->make( 'WPML\Auryn\Test\RequiresInterface');
        $this->assertInstanceOf( 'WPML\Auryn\Test\RequiresInterface', $obj);
    }

    public function testMakeInstancePassesNullCtorParameterIfNoTypehintOrDefaultCanBeDetermined()
    {
        $injector = new Injector;
        $nullCtorParamObj = $injector->make( 'WPML\Auryn\Test\ProvTestNoDefinitionNullDefaultClass');
        $this->assertEquals(new ProvTestNoDefinitionNullDefaultClass, $nullCtorParamObj);
        $this->assertNull($nullCtorParamObj->arg);
    }

    public function testMakeInstanceReturnsSharedInstanceIfAvailable()
    {
        $injector = new Injector;
        $injector->define( 'WPML\Auryn\Test\RequiresInterface', array('dep' =>  'WPML\Auryn\Test\DepImplementation'));
        $injector->share( 'WPML\Auryn\Test\RequiresInterface');
        $injected = $injector->make( 'WPML\Auryn\Test\RequiresInterface');

        $this->assertEquals('something', $injected->testDep->testProp);
        $injected->testDep->testProp = 'something else';

        $injected2 = $injector->make( 'WPML\Auryn\Test\RequiresInterface');
        $this->assertEquals('something else', $injected2->testDep->testProp);
    }

    /**
     * @expectedException \WPML\Auryn\InjectionException
     * @expectedExceptionMessage Could not make ClassThatDoesntExist: Class ClassThatDoesntExist does not exist
     */
    public function testMakeInstanceThrowsExceptionOnClassLoadFailure()
    {
        $injector = new Injector;
        $injector->make('ClassThatDoesntExist');
    }

    public function testMakeInstanceUsesCustomDefinitionIfSpecified()
    {
        $injector = new Injector;
        $injector->define( 'WPML\Auryn\Test\TestNeedsDep', array('testDep'=> 'WPML\Auryn\Test\TestDependency'));
        $injected = $injector->make( 'WPML\Auryn\Test\TestNeedsDep', array('testDep'=> 'WPML\Auryn\Test\TestDependency2'));
        $this->assertEquals('testVal2', $injected->testDep->testProp);
    }

    public function testMakeInstanceCustomDefinitionOverridesExistingDefinitions()
    {
        $injector = new Injector;
        $injector->define( 'WPML\Auryn\Test\InjectorTestChildClass', array(':arg1'=>'First argument', ':arg2'=>'Second argument'));
        $injected = $injector->make( 'WPML\Auryn\Test\InjectorTestChildClass', array(':arg1'=>'Override'));
        $this->assertEquals('Override', $injected->arg1);
        $this->assertEquals('Second argument', $injected->arg2);
    }

    public function testMakeInstanceStoresShareIfMarkedWithNullInstance()
    {
        $injector = new Injector;
        $injector->share( 'WPML\Auryn\Test\TestDependency');
        $injector->make( 'WPML\Auryn\Test\TestDependency');
    }

    public function testMakeInstanceUsesReflectionForUnknownParamsInMultiBuildWithDeps()
    {
        $injector = new Injector;
        $obj = $injector->make( 'WPML\Auryn\Test\TestMultiDepsWithCtor', array('val1'=> 'WPML\Auryn\Test\TestDependency'));
        $this->assertInstanceOf( 'WPML\Auryn\Test\TestMultiDepsWithCtor', $obj);

        $obj = $injector->make( 'WPML\Auryn\Test\NoTypehintNoDefaultConstructorClass',
            array('val1'=> 'WPML\Auryn\Test\TestDependency')
        );
        $this->assertInstanceOf( 'WPML\Auryn\Test\NoTypehintNoDefaultConstructorClass', $obj);
        $this->assertNull($obj->testParam);
    }

    /**
     * @requires PHP 5.6
     */
    public function testMakeInstanceUsesReflectionForUnknownParamsInMultiBuildWithDepsAndVariadics()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped("HHVM doesn't support variadics with type declarations.");
        }

        require_once __DIR__ . "/fixtures_5_6.php";

        $injector = new Injector;
        $obj = $injector->make( 'WPML\Auryn\Test\NoTypehintNoDefaultConstructorVariadicClass',
            array('val1'=> 'WPML\Auryn\Test\TestDependency')
        );
        $this->assertInstanceOf( 'WPML\Auryn\Test\NoTypehintNoDefaultConstructorVariadicClass', $obj);
        $this->assertEquals(array(), $obj->testParam);
    }

    /**
     * @requires PHP 5.6
     */
    public function testMakeInstanceUsesReflectionForUnknownParamsWithDepsAndVariadicsWithTypeHint()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped("HHVM doesn't support variadics with type declarations.");
        }

        require_once __DIR__ . "/fixtures_5_6.php";

        $injector = new Injector;
        $obj = $injector->make( 'WPML\Auryn\Test\TypehintNoDefaultConstructorVariadicClass',
            array('arg'=> 'WPML\Auryn\Test\TestDependency')
        );
        $this->assertInstanceOf( 'WPML\Auryn\Test\TypehintNoDefaultConstructorVariadicClass', $obj);
        $this->assertInternalType("array", $obj->testParam);
        $this->assertInstanceOf( 'WPML\Auryn\Test\TestDependency', $obj->testParam[0]);
    }

    /**
     * @expectedException \WPML\Auryn\InjectionException
     * @expectedExceptionMessage No definition available to provision typeless parameter $val at position 0 in WPML\Auryn\Test\InjectorTestCtorParamWithNoTypehintOrDefault::__construct() declared in WPML\Auryn\Test\InjectorTestCtorParamWithNoTypehintOrDefault::
     * @expectedExceptionCode \WPML\Auryn\Injector::E_UNDEFINED_PARAM
     */
    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithoutDefinitionOrDefault()
    {
        $injector = new Injector;
        $injector->make( 'WPML\Auryn\Test\InjectorTestCtorParamWithNoTypehintOrDefault');
    }

    /**
     * @expectedException \WPML\Auryn\InjectionException
     * @expectedExceptionMessage No definition available to provision typeless parameter $val at position 0 in WPML\Auryn\Test\InjectorTestCtorParamWithNoTypehintOrDefault::__construct() declared in WPML\Auryn\Test\InjectorTestCtorParamWithNoTypehintOrDefault::
     * @expectedExceptionCode \WPML\Auryn\Injector::E_UNDEFINED_PARAM
     */
    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithoutDefinitionOrDefaultThroughAliasedTypehint()
    {
        $injector = new Injector;
        $injector->alias( 'WPML\Auryn\Test\TestNoExplicitDefine',  'WPML\Auryn\Test\InjectorTestCtorParamWithNoTypehintOrDefault');
        $injector->make( 'WPML\Auryn\Test\InjectorTestCtorParamWithNoTypehintOrDefaultDependent');
    }

    /**
     * @TODO
     * @expectedException \WPML\Auryn\InjectorException
     * @expectedExceptionMessage Injection definition required for interface WPML\Auryn\Test\DepInterface
     */
    public function testMakeInstanceThrowsExceptionOnUninstantiableTypehintWithoutDefinition()
    {
        $injector = new Injector;
        $injector->make( 'WPML\Auryn\Test\RequiresInterface');
    }

    public function testTypelessDefineForDependency()
    {
        $thumbnailSize = 128;
        $injector = new Injector;
        $injector->defineParam('thumbnailSize', $thumbnailSize);
        $testClass = $injector->make( 'WPML\Auryn\Test\RequiresDependencyWithTypelessParameters');
        $this->assertEquals($thumbnailSize, $testClass->getThumbnailSize(), 'Typeless define was not injected correctly.');
    }

    public function testTypelessDefineForAliasedDependency()
    {
        $injector = new Injector;
        $injector->defineParam('val', 42);

        $injector->alias( 'WPML\Auryn\Test\TestNoExplicitDefine',  'WPML\Auryn\Test\ProviderTestCtorParamWithNoTypehintOrDefault');
        $obj = $injector->make( 'WPML\Auryn\Test\ProviderTestCtorParamWithNoTypehintOrDefaultDependent');
    }

    public function testMakeInstanceInjectsRawParametersDirectly()
    {
        $injector = new Injector;
        $injector->define( 'WPML\Auryn\Test\InjectorTestRawCtorParams', array(
            ':string' => 'string',
            ':obj' => new \StdClass,
            ':int' => 42,
            ':array' => array(),
            ':float' => 9.3,
            ':bool' => true,
            ':null' => null,
        ));

        $obj = $injector->make( 'WPML\Auryn\Test\InjectorTestRawCtorParams');
        $this->assertInternalType('string', $obj->string);
        $this->assertInstanceOf('StdClass', $obj->obj);
        $this->assertInternalType('int', $obj->int);
        $this->assertInternalType('array', $obj->array);
        $this->assertInternalType('float', $obj->float);
        $this->assertInternalType('bool', $obj->bool);
        $this->assertNull($obj->null);
    }

    /**
     * @TODO
     * @expectedException \Exception
     * @expectedExceptionMessage
     */
    public function testMakeInstanceThrowsExceptionWhenDelegateDoes()
    {
        $injector= new Injector;

        $callable = $this->getMock(
            'CallableMock',
            array('__invoke')
        );

        $injector->delegate('TestDependency', $callable);

        $callable->expects($this->once())
            ->method('__invoke')
            ->will($this->throwException(new \Exception()));

        $injector->make('TestDependency');
    }

    public function testMakeInstanceHandlesNamespacedClasses()
    {
        $injector = new Injector;
        $injector->make( 'WPML\Auryn\Test\SomeClassName');
    }

    public function testMakeInstanceDelegate()
    {
        $injector= new Injector;

        $callable = $this->getMock(
            'CallableMock',
            array('__invoke')
        );
        $callable->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue(new TestDependency()));

        $injector->delegate( 'WPML\Auryn\Test\TestDependency', $callable);

        $obj = $injector->make( 'WPML\Auryn\Test\TestDependency');

        $this->assertInstanceOf( 'WPML\Auryn\Test\TestDependency', $obj);
    }

    public function testMakeInstanceWithStringDelegate()
    {
        $injector= new Injector;
        $injector->delegate('StdClass',  'WPML\Auryn\Test\StringStdClassDelegateMock');
        $obj = $injector->make('StdClass');
        $this->assertEquals(42, $obj->test);
    }

    /**
     * @expectedException \WPML\Auryn\ConfigException
     * @expectedExceptionMessage WPML\Auryn\Injector::delegate expects a valid callable or executable class::method string at Argument 2 but received 'StringDelegateWithNoInvokeMethod'
     */
    public function testMakeInstanceThrowsExceptionIfStringDelegateClassHasNoInvokeMethod()
    {
        $injector= new Injector;
        $injector->delegate('StdClass', 'StringDelegateWithNoInvokeMethod');
    }

    /**
     * @expectedException \WPML\Auryn\ConfigException
     * @expectedExceptionMessage WPML\Auryn\Injector::delegate expects a valid callable or executable class::method string at Argument 2 but received 'SomeClassThatDefinitelyDoesNotExistForReal'
     */
    public function testMakeInstanceThrowsExceptionIfStringDelegateClassInstantiationFails()
    {
        $injector= new Injector;
        $injector->delegate('StdClass', 'SomeClassThatDefinitelyDoesNotExistForReal');
    }

    /**
     * @expectedException \WPML\Auryn\InjectionException
     * @expectedExceptionMessage Injection definition required for interface WPML\Auryn\Test\DepInterface
     */
    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithNoDefinition()
    {
        $injector = new Injector;
        $injector->make( 'WPML\Auryn\Test\RequiresInterface');
    }

    public function testDefineAssignsPassedDefinition()
    {
        $injector = new Injector;
        $definition = array('dep' =>  'WPML\Auryn\Test\DepImplementation');
        $injector->define( 'WPML\Auryn\Test\RequiresInterface', $definition);
        $this->assertInstanceOf( 'WPML\Auryn\Test\RequiresInterface', $injector->make( 'WPML\Auryn\Test\RequiresInterface'));
    }

    public function testShareStoresSharedInstanceAndReturnsCurrentInstance()
    {
        $injector = new Injector;
        $testShare = new \StdClass;
        $testShare->test = 42;

        $this->assertInstanceOf('WPML\Auryn\Injector', $injector->share($testShare));
        $testShare->test = 'test';
        $this->assertEquals('test', $injector->make('stdclass')->test);
    }

    public function testShareMarksClassSharedOnNullObjectParameter()
    {
        $injector = new Injector;
        $this->assertInstanceOf('WPML\Auryn\Injector', $injector->share('SomeClass'));
    }

    /**
     * @expectedException \WPML\Auryn\ConfigException
     * @expectedExceptionMessage WPML\Auryn\Injector::share() requires a string class name or object instance at Argument 1; integer specified
     */
    public function testShareThrowsExceptionOnInvalidArgument()
    {
        $injector = new Injector;
        $injector->share(42);
    }

    public function testAliasAssignsValueAndReturnsCurrentInstance()
    {
        $injector = new Injector;
        $this->assertInstanceOf('WPML\Auryn\Injector', $injector->alias('DepInterface',  'WPML\Auryn\Test\DepImplementation'));
    }

    public function provideInvalidDelegates()
    {
        return array(
            array(new \StdClass),
            array(42),
            array(true)
        );
    }

    /**
     * @dataProvider provideInvalidDelegates
     * @expectedException \WPML\Auryn\ConfigException
     * @expectedExceptionMessage WPML\Auryn\Injector::delegate expects a valid callable or executable class::method string at Argument 2
     */
    public function testDelegateThrowsExceptionIfDelegateIsNotCallableOrString($badDelegate)
    {
        $injector = new Injector;
        $injector->delegate( 'WPML\Auryn\Test\TestDependency', $badDelegate);
    }

    public function testDelegateInstantiatesCallableClassString()
    {
        $injector = new Injector;
        $injector->delegate( 'WPML\Auryn\Test\MadeByDelegate',  'WPML\Auryn\Test\CallableDelegateClassTest');
        $this->assertInstanceof( 'WPML\Auryn\Test\MadeByDelegate', $injector->make( 'WPML\Auryn\Test\MadeByDelegate'));
    }

    public function testDelegateInstantiatesCallableClassArray()
    {
        $injector = new Injector;
        $injector->delegate( 'WPML\Auryn\Test\MadeByDelegate', array( 'WPML\Auryn\Test\CallableDelegateClassTest', '__invoke'));
        $this->assertInstanceof( 'WPML\Auryn\Test\MadeByDelegate', $injector->make( 'WPML\Auryn\Test\MadeByDelegate'));
    }

    public function testUnknownDelegationFunction()
    {
        $injector = new Injector;
        try {
            $injector->delegate( 'WPML\Auryn\Test\DelegatableInterface', 'FunctionWhichDoesNotExist');
            $this->fail("Delegation was supposed to fail.");
        } catch (\WPML\Auryn\InjectorException $ie) {
            $this->assertContains('FunctionWhichDoesNotExist', $ie->getMessage());
            $this->assertEquals(\WPML\Auryn\Injector::E_DELEGATE_ARGUMENT, $ie->getCode());
        }
    }

    public function testUnknownDelegationMethod()
    {
        $injector = new Injector;
        try {
            $injector->delegate( 'WPML\Auryn\Test\DelegatableInterface', array('stdClass', 'methodWhichDoesNotExist'));
            $this->fail("Delegation was supposed to fail.");
        } catch (\WPML\Auryn\InjectorException $ie) {
            $this->assertContains('stdClass', $ie->getMessage());
            $this->assertContains('methodWhichDoesNotExist', $ie->getMessage());
            $this->assertEquals(\WPML\Auryn\Injector::E_DELEGATE_ARGUMENT, $ie->getCode());
        }
    }

    /**
     * @dataProvider provideExecutionExpectations
     */
    public function testProvisionedInvokables($toInvoke, $definition, $expectedResult)
    {
        $injector = new Injector;
        $this->assertEquals($expectedResult, $injector->execute($toInvoke, $definition));
    }

    public function provideExecutionExpectations()
    {
        $return = array();

        // 0 -------------------------------------------------------------------------------------->

        $toInvoke = array( 'WPML\Auryn\Test\ExecuteClassNoDeps', 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 1 -------------------------------------------------------------------------------------->

        $toInvoke = array(new ExecuteClassNoDeps, 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 2 -------------------------------------------------------------------------------------->

        $toInvoke = array( 'WPML\Auryn\Test\ExecuteClassDeps', 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 3 -------------------------------------------------------------------------------------->

        $toInvoke = array(new ExecuteClassDeps(new TestDependency), 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 4 -------------------------------------------------------------------------------------->

        $toInvoke = array( 'WPML\Auryn\Test\ExecuteClassDepsWithMethodDeps', 'execute');
        $args = array(':arg' => 9382);
        $expectedResult = 9382;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 5 -------------------------------------------------------------------------------------->

        $toInvoke = array( 'WPML\Auryn\Test\ExecuteClassStaticMethod', 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 6 -------------------------------------------------------------------------------------->

        $toInvoke = array(new ExecuteClassStaticMethod, 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 7 -------------------------------------------------------------------------------------->

        $toInvoke =  'WPML\Auryn\Test\ExecuteClassStaticMethod::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 8 -------------------------------------------------------------------------------------->

        $toInvoke = array( 'WPML\Auryn\Test\ExecuteClassRelativeStaticMethod', 'parent::execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 9 -------------------------------------------------------------------------------------->

        $toInvoke =  'WPML\Auryn\Test\testExecuteFunction';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 10 ------------------------------------------------------------------------------------->

        $toInvoke = function () { return 42; };
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 11 ------------------------------------------------------------------------------------->

        $toInvoke = new ExecuteClassInvokable;
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 12 ------------------------------------------------------------------------------------->

        $toInvoke =  'WPML\Auryn\Test\ExecuteClassInvokable';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 13 ------------------------------------------------------------------------------------->

        $toInvoke =  'WPML\Auryn\Test\ExecuteClassNoDeps::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 14 ------------------------------------------------------------------------------------->

        $toInvoke =  'WPML\Auryn\Test\ExecuteClassDeps::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 15 ------------------------------------------------------------------------------------->

        $toInvoke =  'WPML\Auryn\Test\ExecuteClassStaticMethod::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 16 ------------------------------------------------------------------------------------->

        $toInvoke =  'WPML\Auryn\Test\ExecuteClassRelativeStaticMethod::parent::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 17 ------------------------------------------------------------------------------------->

        $toInvoke =  'WPML\Auryn\Test\testExecuteFunctionWithArg';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 18 ------------------------------------------------------------------------------------->

        $toInvoke = function () {
            return 42;
        };
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);


        if (PHP_VERSION_ID > 50400) {
            // 19 ------------------------------------------------------------------------------------->

            $object = new ReturnsCallable('new value');
            $args = array();
            $toInvoke = $object->getCallable();
            $expectedResult = 'new value';
            $return[] = array($toInvoke, $args, $expectedResult);
        }
        // x -------------------------------------------------------------------------------------->

        return $return;
    }

    public function testStaticStringInvokableWithArgument()
    {
        $injector = new Injector;
        $invokable = $injector->buildExecutable( 'WPML\Auryn\Test\ClassWithStaticMethodThatTakesArg::doSomething');
        $this->assertEquals(42, $invokable(41));
    }

    public function testInterfaceFactoryDelegation()
    {
        $injector = new Injector;
        $injector->delegate( 'WPML\Auryn\Test\DelegatableInterface',  'WPML\Auryn\Test\ImplementsInterfaceFactory');
        $requiresDelegatedInterface = $injector->make( 'WPML\Auryn\Test\RequiresDelegatedInterface');
        $requiresDelegatedInterface->foo();
    }

    /**
     * @expectedException \WPML\Auryn\InjectionException
     * @expectedExceptionMessage Could not make WPML\Auryn\Test\TestMissingDependency: Class WPML\Auryn\Test\TypoInTypehint does not exist
     */
    public function testMissingAlias()
    {
        $injector = new Injector;
        $testClass = $injector->make( 'WPML\Auryn\Test\TestMissingDependency');
    }

    public function testAliasingConcreteClasses()
    {
        $injector = new Injector;
        $injector->alias( 'WPML\Auryn\Test\ConcreteClass1',  'WPML\Auryn\Test\ConcreteClass2');
        $obj = $injector->make( 'WPML\Auryn\Test\ConcreteClass1');
        $this->assertInstanceOf( 'WPML\Auryn\Test\ConcreteClass2', $obj);
    }

    public function testSharedByAliasedInterfaceName()
    {
        $injector = new Injector;
        $injector->alias( 'WPML\Auryn\Test\SharedAliasedInterface',  'WPML\Auryn\Test\SharedClass');
        $injector->share( 'WPML\Auryn\Test\SharedAliasedInterface');
        $class = $injector->make( 'WPML\Auryn\Test\SharedAliasedInterface');
        $class2 = $injector->make( 'WPML\Auryn\Test\SharedAliasedInterface');
        $this->assertSame($class, $class2);
    }

    public function testNotSharedByAliasedInterfaceName()
    {
        $injector = new Injector;
        $injector->alias( 'WPML\Auryn\Test\SharedAliasedInterface',  'WPML\Auryn\Test\SharedClass');
        $injector->alias( 'WPML\Auryn\Test\SharedAliasedInterface',  'WPML\Auryn\Test\NotSharedClass');
        $injector->share( 'WPML\Auryn\Test\SharedClass');
        $class = $injector->make( 'WPML\Auryn\Test\SharedAliasedInterface');
        $class2 = $injector->make( 'WPML\Auryn\Test\SharedAliasedInterface');

        $this->assertNotSame($class, $class2);
    }

    public function testSharedByAliasedInterfaceNameReversedOrder()
    {
        $injector = new Injector;
        $injector->share( 'WPML\Auryn\Test\SharedAliasedInterface');
        $injector->alias( 'WPML\Auryn\Test\SharedAliasedInterface',  'WPML\Auryn\Test\SharedClass');
        $class = $injector->make( 'WPML\Auryn\Test\SharedAliasedInterface');
        $class2 = $injector->make( 'WPML\Auryn\Test\SharedAliasedInterface');
        $this->assertSame($class, $class2);
    }

    public function testSharedByAliasedInterfaceNameWithParameter()
    {
        $injector = new Injector;
        $injector->alias( 'WPML\Auryn\Test\SharedAliasedInterface',  'WPML\Auryn\Test\SharedClass');
        $injector->share( 'WPML\Auryn\Test\SharedAliasedInterface');
        $sharedClass = $injector->make( 'WPML\Auryn\Test\SharedAliasedInterface');
        $childClass = $injector->make( 'WPML\Auryn\Test\ClassWithAliasAsParameter');
        $this->assertSame($sharedClass, $childClass->sharedClass);
    }

    public function testSharedByAliasedInstance()
    {
        $injector = new Injector;
        $injector->alias( 'WPML\Auryn\Test\SharedAliasedInterface',  'WPML\Auryn\Test\SharedClass');
        $sharedClass = $injector->make( 'WPML\Auryn\Test\SharedAliasedInterface');
        $injector->share($sharedClass);
        $childClass = $injector->make( 'WPML\Auryn\Test\ClassWithAliasAsParameter');
        $this->assertSame($sharedClass, $childClass->sharedClass);
    }

    public function testMultipleShareCallsDontOverrideTheOriginalSharedInstance()
    {
        $injector = new Injector;
        $injector->share('StdClass');
        $stdClass1 = $injector->make('StdClass');
        $injector->share('StdClass');
        $stdClass2 = $injector->make('StdClass');
        $this->assertSame($stdClass1, $stdClass2);
    }

    public function testDependencyWhereSharedWithProtectedConstructor()
    {
        $injector = new Injector;

        $inner = TestDependencyWithProtectedConstructor::create();
        $injector->share($inner);

        $outer = $injector->make( 'WPML\Auryn\Test\TestNeedsDepWithProtCons');

        $this->assertSame($inner, $outer->dep);
    }

    public function testDependencyWhereShared()
    {
        $injector = new Injector;
        $injector->share( 'WPML\Auryn\Test\ClassInnerB');
        $innerDep = $injector->make( 'WPML\Auryn\Test\ClassInnerB');
        $inner = $injector->make( 'WPML\Auryn\Test\ClassInnerA');
        $this->assertSame($innerDep, $inner->dep);
        $outer = $injector->make( 'WPML\Auryn\Test\ClassOuter');
        $this->assertSame($innerDep, $outer->dep->dep);
    }

    public function testBugWithReflectionPoolIncorrectlyReturningBadInfo()
    {
        $injector = new Injector;
        $obj = $injector->make( 'WPML\Auryn\Test\ClassOuter');
        $this->assertInstanceOf( 'WPML\Auryn\Test\ClassOuter', $obj);
        $this->assertInstanceOf( 'WPML\Auryn\Test\ClassInnerA', $obj->dep);
        $this->assertInstanceOf( 'WPML\Auryn\Test\ClassInnerB', $obj->dep->dep);
    }

    public function provideCyclicDependencies()
    {
        return array(
             'WPML\Auryn\Test\RecursiveClassA' => array( 'WPML\Auryn\Test\RecursiveClassA'),
             'WPML\Auryn\Test\RecursiveClassB' => array( 'WPML\Auryn\Test\RecursiveClassB'),
             'WPML\Auryn\Test\RecursiveClassC' => array( 'WPML\Auryn\Test\RecursiveClassC'),
             'WPML\Auryn\Test\RecursiveClass1' => array( 'WPML\Auryn\Test\RecursiveClass1'),
             'WPML\Auryn\Test\RecursiveClass2' => array( 'WPML\Auryn\Test\RecursiveClass2'),
             'WPML\Auryn\Test\DependsOnCyclic' => array( 'WPML\Auryn\Test\DependsOnCyclic'),
        );
    }

     /**
     * @dataProvider provideCyclicDependencies
     * @expectedException \WPML\Auryn\InjectionException
     * @expectedExceptionCode \WPML\Auryn\Injector::E_CYCLIC_DEPENDENCY
     */
    public function testCyclicDependencies($class)
    {
        $injector = new Injector;
        $injector->make($class);
    }

    public function testNonConcreteDependencyWithDefault()
    {
        $injector = new Injector;
        $class = $injector->make( 'WPML\Auryn\Test\NonConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf( 'WPML\Auryn\Test\NonConcreteDependencyWithDefaultValue', $class);
        $this->assertNull($class->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueThroughAlias()
    {
        $injector = new Injector;
        $injector->alias(
             'WPML\Auryn\Test\DelegatableInterface',
             'WPML\Auryn\Test\ImplementsInterface'
        );
        $class = $injector->make( 'WPML\Auryn\Test\NonConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf( 'WPML\Auryn\Test\NonConcreteDependencyWithDefaultValue', $class);
        $this->assertInstanceOf( 'WPML\Auryn\Test\ImplementsInterface', $class->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueThroughDelegation()
    {
        $injector = new Injector;
        $injector->delegate( 'WPML\Auryn\Test\DelegatableInterface',  'WPML\Auryn\Test\ImplementsInterfaceFactory');
        $class = $injector->make( 'WPML\Auryn\Test\NonConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf( 'WPML\Auryn\Test\NonConcreteDependencyWithDefaultValue', $class);
        $this->assertInstanceOf( 'WPML\Auryn\Test\ImplementsInterface', $class->interface);
    }

    public function testDependencyWithDefaultValueThroughShare()
    {
        $injector = new Injector;
        //Instance is not shared, null default is used for dependency
        $instance = $injector->make( 'WPML\Auryn\Test\ConcreteDependencyWithDefaultValue');
        $this->assertNull($instance->dependency);

        //Instance is explicitly shared, $instance is used for dependency
        $instance = new \StdClass();
        $injector->share($instance);
        $instance = $injector->make( 'WPML\Auryn\Test\ConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('StdClass', $instance->dependency);
    }

    /**
     * @expectedException \WPML\Auryn\ConfigException
     * @expectedExceptionMessage Cannot share class stdclass because it is currently aliased to WPML\Auryn\Test\SomeOtherClass
     * @expectedExceptionCode \WPML\Auryn\Injector::E_ALIASED_CANNOT_SHARE
     */
    public function testShareAfterAliasException()
    {
        $injector = new Injector();
        $testClass = new \StdClass();
        $injector->alias('StdClass',  'WPML\Auryn\Test\SomeOtherClass');
        $injector->share($testClass);
    }

    public function testShareAfterAliasAliasedClassAllowed()
    {
        $injector = new Injector();
        $testClass = new DepImplementation();
        $injector->alias( 'WPML\Auryn\Test\DepInterface',  'WPML\Auryn\Test\DepImplementation');
        $injector->share($testClass);
        $obj = $injector->make( 'WPML\Auryn\Test\DepInterface');
        $this->assertInstanceOf( 'WPML\Auryn\Test\DepImplementation', $obj);
    }

    public function testAliasAfterShareByStringAllowed()
    {
        $injector = new Injector();
        $injector->share( 'WPML\Auryn\Test\DepInterface');
        $injector->alias( 'WPML\Auryn\Test\DepInterface',  'WPML\Auryn\Test\DepImplementation');
        $obj = $injector->make( 'WPML\Auryn\Test\DepInterface');
        $obj2 = $injector->make( 'WPML\Auryn\Test\DepInterface');
        $this->assertInstanceOf( 'WPML\Auryn\Test\DepImplementation', $obj);
        $this->assertEquals($obj, $obj2);
    }

    public function testAliasAfterShareBySharingAliasAllowed()
    {
        $injector = new Injector();
        $injector->share( 'WPML\Auryn\Test\DepImplementation');
        $injector->alias( 'WPML\Auryn\Test\DepInterface',  'WPML\Auryn\Test\DepImplementation');
        $obj = $injector->make( 'WPML\Auryn\Test\DepInterface');
        $obj2 = $injector->make( 'WPML\Auryn\Test\DepInterface');
        $this->assertInstanceOf( 'WPML\Auryn\Test\DepImplementation', $obj);
        $this->assertEquals($obj, $obj2);
    }

    /**
     * @expectedException \WPML\Auryn\ConfigException
     * @expectedExceptionMessage Cannot alias class stdclass to WPML\Auryn\Test\SomeOtherClass because it is currently shared
     * @expectedExceptionCode \WPML\Auryn\Injector::E_SHARED_CANNOT_ALIAS
     */
    public function testAliasAfterShareException()
    {
        $injector = new Injector();
        $testClass = new \StdClass();
        $injector->share($testClass);
        $injector->alias('StdClass',  'WPML\Auryn\Test\SomeOtherClass');
    }

    /**
     * @expectedException \WPML\Auryn\InjectionException
     * @expectedExceptionMessage Cannot instantiate protected/private constructor in class WPML\Auryn\Test\HasNonPublicConstructor
     * @expectedExceptionCode \WPML\Auryn\Injector::E_NON_PUBLIC_CONSTRUCTOR
     */
    public function testAppropriateExceptionThrownOnNonPublicConstructor()
    {
        $injector = new Injector();
        $injector->make( 'WPML\Auryn\Test\HasNonPublicConstructor');
    }

    /**
     * @expectedException \WPML\Auryn\InjectionException
     * @expectedExceptionMessage Cannot instantiate protected/private constructor in class WPML\Auryn\Test\HasNonPublicConstructorWithArgs
     * @expectedExceptionCode \WPML\Auryn\Injector::E_NON_PUBLIC_CONSTRUCTOR
     */
    public function testAppropriateExceptionThrownOnNonPublicConstructorWithArgs()
    {
        $injector = new Injector();
        $injector->make( 'WPML\Auryn\Test\HasNonPublicConstructorWithArgs');
    }

    public function testMakeExecutableFailsOnNonExistentFunction()
    {
        $injector = new Injector();
        $this->setExpectedException(
            'WPML\Auryn\InjectionException',
            'nonExistentFunction',
            \WPML\Auryn\Injector::E_INVOKABLE
        );
        $injector->buildExecutable('nonExistentFunction');
    }

    public function testMakeExecutableFailsOnNonExistentInstanceMethod()
    {
        $injector = new Injector();
        $object = new \StdClass();
        $this->setExpectedException(
            'WPML\Auryn\InjectionException',
            "[object(stdClass), 'nonExistentMethod']",
            \WPML\Auryn\Injector::E_INVOKABLE
        );
        $injector->buildExecutable(array($object, 'nonExistentMethod'));
    }

    public function testMakeExecutableFailsOnNonExistentStaticMethod()
    {
        $injector = new Injector();
        $this->setExpectedException(
            'WPML\Auryn\InjectionException',
            "StdClass::nonExistentMethod",
            \WPML\Auryn\Injector::E_INVOKABLE
        );
        $injector->buildExecutable(array('StdClass', 'nonExistentMethod'));
    }

    /**
     * @expectedException \WPML\Auryn\InjectionException
     * @expectedExceptionMessage Invalid invokable: callable or provisional string required
     * @expectedExceptionCode \WPML\Auryn\Injector::E_INVOKABLE
     */
    public function testMakeExecutableFailsOnClassWithoutInvoke()
    {
        $injector = new Injector();
        $object = new \StdClass();
        $injector->buildExecutable($object);
    }

    /**
     * @expectedException \WPML\Auryn\ConfigException
     * @expectedExceptionMessage Invalid alias: non-empty string required at arguments 1 and 2
     * @expectedExceptionCode \WPML\Auryn\Injector::E_NON_EMPTY_STRING_ALIAS
     */
    public function testBadAlias()
    {
        $injector = new Injector();
        $injector->share( 'WPML\Auryn\Test\DepInterface');
        $injector->alias( 'WPML\Auryn\Test\DepInterface', '');
    }

    public function testShareNewAlias()
    {
        $injector = new Injector();
        $injector->share( 'WPML\Auryn\Test\DepImplementation');
        $injector->alias( 'WPML\Auryn\Test\DepInterface',  'WPML\Auryn\Test\DepImplementation');
    }

    public function testDefineWithBackslashAndMakeWithoutBackslash()
    {
        $injector = new Injector();
        $injector->define( 'WPML\Auryn\Test\SimpleNoTypehintClass', array(':arg' => 'tested'));
        $testClass = $injector->make( 'WPML\Auryn\Test\SimpleNoTypehintClass');
        $this->assertEquals('tested', $testClass->testParam);
    }

    public function testShareWithBackslashAndMakeWithoutBackslash()
    {
        $injector = new Injector();
        $injector->share('\StdClass');
        $classA = $injector->make('StdClass');
        $classA->tested = false;
        $classB = $injector->make('\StdClass');
        $classB->tested = true;

        $this->assertEquals($classA->tested, $classB->tested);
    }

    public function testInstanceMutate()
    {
        $injector = new Injector();
        $injector->prepare('\StdClass', function ($obj, $injector) {
            $obj->testval = 42;
        });
        $obj = $injector->make('StdClass');

        $this->assertSame(42, $obj->testval);
    }

    public function testInterfaceMutate()
    {
        $injector = new Injector();
        $injector->prepare( 'WPML\Auryn\Test\SomeInterface', function ($obj, $injector) {
            $obj->testProp = 42;
        });
        $obj = $injector->make( 'WPML\Auryn\Test\PreparesImplementationTest');

        $this->assertSame(42, $obj->testProp);
    }



    /**
     * Test that custom definitions are not passed through to dependencies.
     * Surprising things would happen if this did occur.
     * @expectedException \WPML\Auryn\InjectionException
     * @expectedExceptionMessage No definition available to provision typeless parameter $foo at position 0 in WPML\Auryn\Test\DependencyWithDefinedParam::__construct() declared in WPML\Auryn\Test\DependencyWithDefinedParam::
     * @expectedExceptionCode \WPML\Auryn\Injector::E_UNDEFINED_PARAM
     */
    public function testCustomDefinitionNotPassedThrough()
    {
        $injector = new Injector();
        $injector->share( 'WPML\Auryn\Test\DependencyWithDefinedParam');
        $injector->make( 'WPML\Auryn\Test\RequiresDependencyWithDefinedParam', array(':foo' => 5));
    }

    public function testDelegationFunction()
    {
        $injector = new Injector();
        $injector->delegate( 'WPML\Auryn\Test\TestDelegationSimple',  'WPML\Auryn\Test\createTestDelegationSimple');
        $obj = $injector->make( 'WPML\Auryn\Test\TestDelegationSimple');
        $this->assertInstanceOf( 'WPML\Auryn\Test\TestDelegationSimple', $obj);
        $this->assertTrue($obj->delegateCalled);
    }

    public function testDelegationDependency()
    {
        $injector = new Injector();
        $injector->delegate(
             'WPML\Auryn\Test\TestDelegationDependency',
             'WPML\Auryn\Test\createTestDelegationDependency'
        );
        $obj = $injector->make( 'WPML\Auryn\Test\TestDelegationDependency');
        $this->assertInstanceOf( 'WPML\Auryn\Test\TestDelegationDependency', $obj);
        $this->assertTrue($obj->delegateCalled);
    }

    public function testExecutableAliasing()
    {
        $injector = new Injector();
        $injector->alias( 'WPML\Auryn\Test\BaseExecutableClass',  'WPML\Auryn\Test\ExtendsExecutableClass');
        $result = $injector->execute(array( 'WPML\Auryn\Test\BaseExecutableClass', 'foo'));
        $this->assertEquals('This is the ExtendsExecutableClass', $result);
    }

    public function testExecutableAliasingStatic()
    {
        $injector = new Injector();
        $injector->alias( 'WPML\Auryn\Test\BaseExecutableClass',  'WPML\Auryn\Test\ExtendsExecutableClass');
        $result = $injector->execute(array( 'WPML\Auryn\Test\BaseExecutableClass', 'bar'));
        $this->assertEquals('This is the ExtendsExecutableClass', $result);
    }

    /**
     * Test coverage for delegate closures that are defined outside
     * of a class.ph
     * @throws \WPML\Auryn\ConfigException
     */
    public function testDelegateClosure()
    {
        $delegateClosure = getDelegateClosureInGlobalScope();
        $injector = new Injector();
        $injector->delegate( 'WPML\Auryn\Test\DelegateClosureInGlobalScope', $delegateClosure);
        $injector->make( 'WPML\Auryn\Test\DelegateClosureInGlobalScope');
    }

    public function testCloningWithServiceLocator()
    {
        $injector = new Injector();
        $injector->share($injector);
        $instance = $injector->make( 'WPML\Auryn\Test\CloneTest');
        $newInjector = $instance->injector;
        $newInstance = $newInjector->make( 'WPML\Auryn\Test\CloneTest');
    }

    public function testAbstractExecute()
    {
        $injector = new Injector();

        $fn = function () {
            return new ConcreteExexcuteTest();
        };

        $injector->delegate( 'WPML\Auryn\Test\AbstractExecuteTest', $fn);
        $result = $injector->execute(array( 'WPML\Auryn\Test\AbstractExecuteTest', 'process'));

        $this->assertEquals('Concrete', $result);
    }

    public function testDebugMake()
    {
        $injector = new Injector();
        try {
            $injector->make( 'WPML\Auryn\Test\DependencyChainTest');
        } catch (InjectionException $ie) {
            $chain = $ie->getDependencyChain();
            $this->assertCount(2, $chain);

            $this->assertEquals( 'wpml\auryn\test\dependencychaintest', $chain[0]);
            $this->assertEquals( 'wpml\auryn\test\depinterface', $chain[1]);
        }
    }

    public function testInspectShares()
    {
        $injector = new Injector();
        $injector->share( 'WPML\Auryn\Test\SomeClassName');

        $inspection = $injector->inspect( 'WPML\Auryn\Test\SomeClassName', \WPML\Auryn\Injector::I_SHARES);
        $this->assertArrayHasKey( 'wpml\auryn\test\someclassname', $inspection[\WPML\Auryn\Injector::I_SHARES]);
    }

    public function testInspectAll()
    {
        $injector = new Injector();

        // \WPML\Auryn\Injector::I_BINDINGS
        $injector->define( 'WPML\Auryn\Test\DependencyWithDefinedParam', array(':arg' => 42));

        // \WPML\Auryn\Injector::I_DELEGATES
        $injector->delegate( 'WPML\Auryn\Test\MadeByDelegate',  'WPML\Auryn\Test\CallableDelegateClassTest');

        // \WPML\Auryn\Injector::I_PREPARES
        $injector->prepare( 'WPML\Auryn\Test\MadeByDelegate', function ($c) {});

        // \WPML\Auryn\Injector::I_ALIASES
        $injector->alias('i', 'WPML\Auryn\Injector');

        // \WPML\Auryn\Injector::I_SHARES
        $injector->share('WPML\Auryn\Injector');

        $all = $injector->inspect();
        $some = $injector->inspect( 'WPML\Auryn\Test\MadeByDelegate');

        $this->assertCount(5, array_filter($all));
        $this->assertCount(2, array_filter($some));
    }

    /**
     * @expectedException \WPML\Auryn\InjectionException
     * @expectedExceptionMessage Making wpml\auryn\test\someclassname did not result in an object, instead result is of type 'NULL'
     * @expectedExceptionCode \WPML\Auryn\Injector::E_MAKING_FAILED
     */
    public function testDelegationDoesntMakeObject()
    {
        $delegate = function () {
            return null;
        };
        $injector = new Injector();
        $injector->delegate( 'WPML\Auryn\Test\SomeClassName', $delegate);
        $injector->make( 'WPML\Auryn\Test\SomeClassName');
    }

    /**
     * @expectedException \WPML\Auryn\InjectionException
     * @expectedExceptionMessage Making wpml\auryn\test\someclassname did not result in an object, instead result is of type 'string'
     * @expectedExceptionCode \WPML\Auryn\Injector::E_MAKING_FAILED
     */
    public function testDelegationDoesntMakeObjectMakesString()
    {
        $delegate = function () {
            return 'ThisIsNotAClass';
        };
        $injector = new Injector();
        $injector->delegate( 'WPML\Auryn\Test\SomeClassName', $delegate);
        $injector->make( 'WPML\Auryn\Test\SomeClassName');
    }

    public function testPrepareInvalidCallable()
    {
        $injector = new Injector;
        $invalidCallable = 'This_does_not_exist';
        $this->setExpectedException('WPML\Auryn\InjectionException', $invalidCallable);
        $injector->prepare("StdClass", $invalidCallable);
    }

    public function testPrepareCallableReplacesObjectWithReturnValueOfSameInterfaceType()
    {
        $injector = new Injector;
        $expected = new SomeImplementation; // <-- implements SomeInterface
        $injector->prepare("WPML\Auryn\Test\SomeInterface", function ($impl) use ($expected) {
            return $expected;
        });
        $actual = $injector->make("WPML\Auryn\Test\SomeImplementation");
        $this->assertSame($expected, $actual);
    }

    public function testPrepareCallableReplacesObjectWithReturnValueOfSameClassType()
    {
        $injector = new Injector;
        $expected = new SomeImplementation; // <-- implements SomeInterface
        $injector->prepare("WPML\Auryn\Test\SomeImplementation", function ($impl) use ($expected) {
            return $expected;
        });
        $actual = $injector->make("WPML\Auryn\Test\SomeImplementation");
        $this->assertSame($expected, $actual);
    }

    public function testChildWithoutConstructorWorks() {

        $injector = new Injector;
        try {
            $injector->define( 'WPML\Auryn\Test\ParentWithConstructor', array(':foo' => 'parent'));
            $injector->define( 'WPML\Auryn\Test\ChildWithoutConstructor', array(':foo' => 'child'));

            $injector->share( 'WPML\Auryn\Test\ParentWithConstructor');
            $injector->share( 'WPML\Auryn\Test\ChildWithoutConstructor');

            $child = $injector->make( 'WPML\Auryn\Test\ChildWithoutConstructor');
            $this->assertEquals('child', $child->foo);

            $parent = $injector->make( 'WPML\Auryn\Test\ParentWithConstructor');
            $this->assertEquals('parent', $parent->foo);
        }
        catch (InjectionException $ie) {
            echo $ie->getMessage();
            $this->fail("Auryn failed to locate the ");
        }
    }

    /**
     * @expectedException \WPML\Auryn\InjectionException
     * @expectedExceptionCode \WPML\Auryn\Injector::E_UNDEFINED_PARAM
     * @expectedExceptionMessage No definition available to provision typeless parameter $foo at position 0 in WPML\Auryn\Test\ChildWithoutConstructor::__construct() declared in WPML\Auryn\Test\ParentWithConstructor
     */
    public function testChildWithoutConstructorMissingParam()
    {
        $injector = new Injector;
        $injector->define( 'WPML\Auryn\Test\ParentWithConstructor', array(':foo' => 'parent'));
        $injector->make( 'WPML\Auryn\Test\ChildWithoutConstructor');
    }

    public function testInstanceClosureDelegates()
    {
        $injector = new Injector;
        $injector->delegate( 'WPML\Auryn\Test\DelegatingInstanceA', function (DelegateA $d) {
            return new DelegatingInstanceA($d);
        });
        $injector->delegate( 'WPML\Auryn\Test\DelegatingInstanceB', function (DelegateB $d) {
            return new DelegatingInstanceB($d);
        });

        $a = $injector->make( 'WPML\Auryn\Test\DelegatingInstanceA');
        $b = $injector->make( 'WPML\Auryn\Test\DelegatingInstanceB');

        $this->assertInstanceOf( 'WPML\Auryn\Test\DelegateA', $a->a);
        $this->assertInstanceOf( 'WPML\Auryn\Test\DelegateB', $b->b);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Exception in constructor
     */
    public function testThatExceptionInConstructorDoesntCauseCyclicDependencyException()
    {
        $injector = new Injector;

        try {
            $injector->make( 'WPML\Auryn\Test\ThrowsExceptionInConstructor');
        }
        catch (\Exception $e) {
        }

        $injector->make( 'WPML\Auryn\Test\ThrowsExceptionInConstructor');
    }
}
