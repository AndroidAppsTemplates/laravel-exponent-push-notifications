<?php

namespace NotificationChannels\ExpoPushNotifications\Test;

use ExponentPhpSDK\Expo;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Illuminate\Events\Dispatcher;
use Illuminate\Validation\Factory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use NotificationChannels\ExpoPushNotifications\ExpoChannel;
use NotificationChannels\ExpoPushNotifications\Http\ExpoController;

class ExpoControllerTest extends TestCase
{
    /**
     * @var ExpoChannel
     */
    protected $expoChannel;

    /**
     * @var ExpoController
     */
    protected $expoController;

    public function setUp()
    {
        parent::setUp();

        $this->expoChannel = new ExpoChannel(Expo::normalSetup(), new Dispatcher());

        $this->expoController = new ExpoController($this->expoChannel);

        // We will fake an authenticated user
        Auth::shouldReceive('user')->andReturn(new User());
    }

    public function tearDown()
    {
        \Mockery::close();

        parent::tearDown();
    }

    /** @test */
    public function aDeviceCanSubscribeToTheSystem()
    {
        // We will fake a request with the following data
        $data = ['expo_token' => 'ExponentPushToken[fakeToken]'];
        $request = $this->mockRequest($data);
        $request->shouldReceive('get')->with('expo_token')->andReturn($data['expo_token']);

        $this->mockValidator(false);

        /** @var Request $request */
        $response = $this->expoController->subscribe($request);
        $response = json_decode($response->content());

        // The response should contain a succeeded status
        $this->assertEquals('succeeded', $response->status);
        // The response should return the registered token
        $this->assertEquals($data['expo_token'], $response->expo_token);
    }

    /** @test */
    public function subscribeReturnsErrorResponseIfTokenInvalid()
    {
        // We will fake a request with no data
        $request = $this->mockRequest([]);

        $this->mockValidator(true);

        /** @var Request $request */
        $response = $this->expoController->subscribe($request);

        // The response should contain a failed status
        $this->assertEquals('failed', json_decode($response->content())->status);
        // The response status should be 422
        $this->assertEquals(422, $response->getStatusCode());
    }

    /** @test */
    public function subscribeReturnsErrorResponseIfExceptionIsThrown()
    {
        // We will fake a request with the following data
        $data = ['expo_token' => 'ExponentPushToken[fakeToken]'];
        $request = $this->mockRequest($data);
        $request->shouldReceive('get')->andReturn($data['expo_token']);

        $this->mockValidator(false);

        $expo = \Mockery::mock(Expo::class);
        $expo->shouldReceive('subscribe')->andThrow(\Exception::class);

        /** @var Expo $expo */
        $expoChannel = new ExpoChannel($expo, new Dispatcher());

        /** @var Request $request */
        $response = (new ExpoController($expoChannel))->subscribe($request);
        $response = json_decode($response->content());

        $this->assertEquals('failed', $response->status);
    }

    /** @test */
    public function aDeviceCanUnsubscribeFromTheSystem()
    {
        // We will subscribe an interest to the server.
        $token = 'ExponentPushToken[fakeToken]';
        $interest = $this->expoChannel->interestName(new User());
        $this->expoChannel->expo->subscribe($interest, $token);

        $response = $this->expoController->unsubscribe();
        $response = json_decode($response->content());

        // The response should contain a deleted property with value true
        $this->assertTrue($response->deleted);
    }

    /** @test */
    public function unsubscribeReturnsErrorResponseIfExceptionIsThrown()
    {
        $expo = \Mockery::mock(Expo::class);
        $expo->shouldReceive('unsubscribe')->andThrow(\Exception::class);

        /** @var Expo $expo */
        $response = (new ExpoController(new ExpoChannel($expo, new Dispatcher())))->unsubscribe();
        $response = json_decode($response->content());

        $this->assertEquals('failed', $response->status);
    }

    /**
     * Mocks a request for the ExpoController.
     *
     * @param $data
     *
     * @return \Mockery\MockInterface
     */
    private function mockRequest($data)
    {
        $request = \Mockery::mock(Request::class);
        $request->shouldReceive('all')->andReturn($data);

        return $request;
    }

    /**
     * @param bool $fails
     *
     * @return \Mockery\MockInterface
     */
    private function mockValidator(bool $fails)
    {
        $validator = \Mockery::mock(\Illuminate\Validation\Validator::class);

        $validation = \Mockery::mock(Factory::class);
        $validation->shouldReceive('make')->once()->andReturn($validator);

        $validator->shouldReceive('fails')->once()->andReturn($fails);

        Validator::swap($validation);

        return $validator;
    }
}

class User
{
    public function getKey()
    {
        return 1;
    }
}
