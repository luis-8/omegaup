<?php
// phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

/**
 * Description of ContestUsersTest
 */

class ContestUsersTest extends \OmegaUp\Test\ControllerTestCase {
    public function testContestUsersValid() {
        // Get a contest
        $contestData = \OmegaUp\Test\Factories\Contest::createContest();

        // Create 10 users
        $n = 10;
        $users = [];
        $identities = [];

        ['user' => $users[0], 'identity' => $identities[0]] = \OmegaUp\Test\Factories\User::createUser(new \OmegaUp\Test\Factories\UserParams([
            'username' => 'test_contest_user_0',
        ]));
        \OmegaUp\Test\Factories\Contest::addUser(
            $contestData,
            $identities[0]
        );

        ['user' => $users[1], 'identity' => $identities[1]] = \OmegaUp\Test\Factories\User::createUser(new \OmegaUp\Test\Factories\UserParams([
            'username' => 'test_contest_user_1',
        ]));
        \OmegaUp\Test\Factories\Contest::addUser(
            $contestData,
            $identities[1]
        );
        for ($i = 2; $i < $n; $i++) {
            ['user' => $users[$i], 'identity' => $identities[$i]] = \OmegaUp\Test\Factories\User::createUser();
            \OmegaUp\Test\Factories\Contest::addUser(
                $contestData,
                $identities[$i]
            );
        }

        // Create a n+1 user who will just join to the contest without being
        // added via API. For public contests, by entering to the contest, the user should be in
        // the list of contest's users.
        ['user' => $nonRegisteredUser, 'identity' => $nonRegisteredIdentity] = \OmegaUp\Test\Factories\User::createUser();
        \OmegaUp\Test\Factories\Contest::openContest(
            $contestData['contest'],
            $nonRegisteredIdentity
        );

        // Log in with the admin of the contest
        $login = self::login($contestData['director']);
        $r = new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'contest_alias' => $contestData['request']['alias'],
        ]);

        // Call API
        $response = \OmegaUp\Controllers\Contest::apiUsers($r);

        // Check that we have n+1 users
        $this->assertCount($n + 1, $response['users']);

        // Call search API
        $response = \OmegaUp\Controllers\Contest::apiSearchUsers(
            new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'contest_alias' => $contestData['request']['alias'],
                'query' => 'test_contest_user_'
            ])
        )['results'];
        $this->assertCount(2, $response);
    }

    public function testContestActivityReport() {
        // Get a contest
        $contestData = \OmegaUp\Test\Factories\Contest::createContest();

        ['user' => $user, 'identity' => $identity] = \OmegaUp\Test\Factories\User::createUser();
        \OmegaUp\Test\Factories\Contest::openContest(
            $contestData['contest'],
            $identity
        );

        $userLogin = self::login($identity);
        \OmegaUp\Controllers\Contest::apiDetails(new \OmegaUp\Request([
            'auth_token' => $userLogin->auth_token,
            'contest_alias' => $contestData['request']['alias'],
        ]));

        // Call API
        $directorLogin = self::login($contestData['director']);
        $response = \OmegaUp\Controllers\Contest::apiActivityReport(new \OmegaUp\Request([
            'auth_token' => $directorLogin->auth_token,
            'contest_alias' => $contestData['request']['alias'],
        ]));

        // Check that we have entries in the log.
        $this->assertEquals(1, count($response['events']));
        $this->assertEquals(
            $identity->username,
            $response['events'][0]['username']
        );
        $this->assertEquals(0, $response['events'][0]['ip']);
        $this->assertEquals('open', $response['events'][0]['event']['name']);
    }

    public function testFutureContestIntro() {
        // Get a contest
        $startTime =  new \OmegaUp\Timestamp(\OmegaUp\Time::get() + 60 * 60);
        $finishTime =  new \OmegaUp\Timestamp(\OmegaUp\Time::get() + 120 * 60);
        $contestData = \OmegaUp\Test\Factories\Contest::createContest(
            new \OmegaUp\Test\Factories\ContestParams([
                'requestsUserInformation' => 'optional',
                'startTime' => $startTime,
                'finishTime' => $finishTime,
            ])
        );
        // Create user
        ['identity' => $identity] = \OmegaUp\Test\Factories\User::createUser();

        // Add user to our contest
        \OmegaUp\Test\Factories\Contest::addUser(
            $contestData,
            $identity
        );

        $userLogin = self::login($identity);
        $this->assertTrue(
            \OmegaUp\Controllers\Contest::shouldShowIntro(
                $identity,
                $contestData['contest']
            )
        );
        $contestDetails = \OmegaUp\Controllers\Contest::getContestDetailsForTypeScript(
            new \OmegaUp\Request([
                'auth_token' => $userLogin->auth_token,
                'contest_alias' => $contestData['request']['alias'],
            ])
        )['smartyProperties']['payload'];

        $this->assertEquals(
            $contestDetails['contest']['start_time']->time,
            $startTime->time
        );
    }

    public function testContestDataForTypescript() {
        // Get a contest
        $contestData = \OmegaUp\Test\Factories\Contest::createContest(
            new \OmegaUp\Test\Factories\ContestParams([
                'requestsUserInformation' => 'optional',
            ])
        );
        // Create user
        ['identity' => $identity] = \OmegaUp\Test\Factories\User::createUser();

        // Add user to our contest
        \OmegaUp\Test\Factories\Contest::addUser(
            $contestData,
            $identity
        );

        $userLogin = self::login($identity);
        $contestDetails = \OmegaUp\Controllers\Contest::getContestDetailsForTypeScript(
            new \OmegaUp\Request([
                'auth_token' => $userLogin->auth_token,
                'contest_alias' => $contestData['request']['alias'],
            ])
        )['smartyProperties']['payload'];

        \OmegaUp\Controllers\Contest::apiOpen(new \OmegaUp\Request([
            'contest_alias' => $contestData['request']['alias'],
            'auth_token' => $userLogin->auth_token,
            'privacy_git_object_id' =>
                $contestDetails['privacyStatement']['gitObjectId'],
            'statement_type' =>
                $contestDetails['privacyStatement']['statementType'],
            'share_user_information' => 1,
        ]));

        $contestDetails = \OmegaUp\Controllers\Contest::getContestDetailsForTypeScript(
            new \OmegaUp\Request([
                'auth_token' => $userLogin->auth_token,
                'contest_alias' => $contestData['request']['alias'],
            ])
        )['smartyProperties']['payload'];

        // adminPayload object should not exist
        $this->assertArrayNotHasKey('adminPayload', $contestDetails);
    }

    public function testContestParticipantsReport() {
        // Get a contest
        $contestData = \OmegaUp\Test\Factories\Contest::createContest(
            new \OmegaUp\Test\Factories\ContestParams([
                'requestsUserInformation' => 'optional',
            ])
        );
        $identity = [];
        $numberOfStudents = 3;
        foreach (range(0, $numberOfStudents - 1) as $studentIndex) {
            // Create users
            [
                'identity' => $identity[$studentIndex],
            ] = \OmegaUp\Test\Factories\User::createUser();

            // Add users to our private contest
            \OmegaUp\Test\Factories\Contest::addUser(
                $contestData,
                $identity[$studentIndex]
            );
        }

        $userLogin = self::login($identity[0]);
        $this->assertTrue(
            \OmegaUp\Controllers\Contest::shouldShowIntro(
                $identity[0],
                $contestData['contest']
            )
        );
        $contestDetails = \OmegaUp\Controllers\Contest::getContestDetailsForTypeScript(
            new \OmegaUp\Request([
                'auth_token' => $userLogin->auth_token,
                'contest_alias' => $contestData['request']['alias'],
            ])
        )['smartyProperties']['payload'];

        $this->assertEquals(
            $contestData['director']->username,
            $contestDetails['contest']['director']
        );
        // Explicitly join contest
        \OmegaUp\Controllers\Contest::apiOpen(new \OmegaUp\Request([
            'contest_alias' => $contestData['request']['alias'],
            'auth_token' => $userLogin->auth_token,
            'privacy_git_object_id' =>
                $contestDetails['privacyStatement']['gitObjectId'],
            'statement_type' =>
                $contestDetails['privacyStatement']['statementType'],
            'share_user_information' => 1,
        ]));

        // Call API
        $directorLogin = self::login($contestData['director']);

        $r = new \OmegaUp\Request([
            'contest_alias' => $contestData['request']['alias'],
            'auth_token' => $directorLogin->auth_token
        ]);

        $response = \OmegaUp\Controllers\Contest::apiContestants($r);

        // There are three participants in the current contest
        $this->assertEquals(3, count($response['contestants']));

        // But only one participant has accepted share user information
        $this->assertEquals(1, self::numberOfUsersSharingBasicInformation(
            $response['contestants']
        ));

        $userLogin = self::login($identity[1]);

        // Explicitly join contest
        \OmegaUp\Controllers\Contest::apiOpen(new \OmegaUp\Request([
            'contest_alias' => $contestData['request']['alias'],
            'auth_token' => $userLogin->auth_token,
            'privacy_git_object_id' =>
                $contestDetails['privacyStatement']['gitObjectId'],
            'statement_type' =>
                $contestDetails['privacyStatement']['statementType'],
            'share_user_information' => 0,
        ]));

        $response = \OmegaUp\Controllers\Contest::apiContestants($r);

        // The number of participants sharing their information still remains the same
        $this->assertEquals(1, self::numberOfUsersSharingBasicInformation(
            $response['contestants']
        ));

        $userLogin = self::login($identity[2]);

        // Explicitly join contest
        \OmegaUp\Controllers\Contest::apiOpen(new \OmegaUp\Request([
            'contest_alias' => $contestData['request']['alias'],
            'auth_token' => $userLogin->auth_token,
            'privacy_git_object_id' =>
                $contestDetails['privacyStatement']['gitObjectId'],
            'statement_type' =>
                $contestDetails['privacyStatement']['statementType'],
            'share_user_information' => 1,
        ]));

        $response = \OmegaUp\Controllers\Contest::apiContestants($r);

        // Now there are two participants sharing their information
        $this->assertEquals(2, self::numberOfUsersSharingBasicInformation(
            $response['contestants']
        ));
    }

    public function testContestCanBeSeenByUnloggedUsers() {
        // Get a contest
        $contestData = \OmegaUp\Test\Factories\Contest::createContest();

        $this->assertTrue(
            \OmegaUp\Controllers\Contest::shouldShowIntro(
                null,
                $contestData['contest']
            )
        );
    }

    public function testNeedsBasicInformation() {
        // Get a contest
        $contestData = \OmegaUp\Test\Factories\Contest::createContest(new \OmegaUp\Test\Factories\ContestParams([
            'basicInformation' => 'true',
        ]));

        // Create and login a user to view the contest
        ['user' => $user, 'identity' => $identity] = \OmegaUp\Test\Factories\User::createUser();
        $userLogin = self::login($identity);

        // Contest intro can be shown by the user
        $this->assertTrue(
            \OmegaUp\Controllers\Contest::shouldShowIntro(
                $identity,
                $contestData['contest']
            )
        );

        // Contest needs basic information for the user
        $contestDetails = \OmegaUp\Controllers\Contest::getContestDetailsForTypeScript(
            new \OmegaUp\Request([
                'auth_token' => $userLogin->auth_token,
                'contest_alias' => $contestData['request']['alias'],
            ])
        )['smartyProperties']['payload'];

        $this->assertTrue($contestDetails['needsBasicInformation']);
    }

    public function testBasicContestPractice() {
        // Get a contest in the past
        $startTime =  new \OmegaUp\Timestamp(\OmegaUp\Time::get() - 120 * 60);
        $finishTime =  new \OmegaUp\Timestamp(\OmegaUp\Time::get() - 60 * 60);
        $contestData = \OmegaUp\Test\Factories\Contest::createContest(
            new \OmegaUp\Test\Factories\ContestParams([
                'startTime' => $startTime,
                'finishTime' => $finishTime,
                'admissionMode' => 'private',
            ])
        );
        ['identity' => $identity] = \OmegaUp\Test\Factories\User::createUser();

        // Add user to our private contest
        \OmegaUp\Test\Factories\Contest::addUser($contestData, $identity);

        $userLogin = self::login($identity);
        $this->assertTrue(
            \OmegaUp\Controllers\Contest::shouldShowIntro(
                $identity,
                $contestData['contest']
            )
        );

        $contestDetails = \OmegaUp\Controllers\Contest::getContestPracticeDetailsForTypeScript(
            new \OmegaUp\Request([
                'auth_token' => $userLogin->auth_token,
                'contest_alias' => $contestData['request']['alias'],
            ])
        )['smartyProperties']['payload'];

        $this->assertEquals(
            $contestData['director']->username,
            $contestDetails['contest']['director']
        );
    }

    public function testContestPracticeForNonRegisteredUsers() {
        // Get a contest in the past
        $startTime =  new \OmegaUp\Timestamp(\OmegaUp\Time::get() - 120 * 60);
        $finishTime =  new \OmegaUp\Timestamp(\OmegaUp\Time::get() - 60 * 60);
        $contestData = \OmegaUp\Test\Factories\Contest::createContest(
            new \OmegaUp\Test\Factories\ContestParams([
                'startTime' => $startTime,
                'finishTime' => $finishTime,
                'admissionMode' => 'public',
            ])
        );

        // Non-registered users can access public contests in practice mode
        [
            'identity' => $nonRegisteredIdentity,
        ] = \OmegaUp\Test\Factories\User::createUser();
        $userLogin = self::login($nonRegisteredIdentity);
        $contestDetails = \OmegaUp\Controllers\Contest::getContestPracticeDetailsForTypeScript(
            new \OmegaUp\Request([
                'auth_token' => $userLogin->auth_token,
                'contest_alias' => $contestData['request']['alias'],
            ])
        )['smartyProperties']['payload'];

        $this->assertEquals(
            $contestData['director']->username,
            $contestDetails['contest']['director']
        );
    }

    public function testProblemsInContestPracticeForNonRegisteredUsers() {
        // Get a contest in the past
        $startTime =  new \OmegaUp\Timestamp(\OmegaUp\Time::get() - 120 * 60);
        $finishTime =  new \OmegaUp\Timestamp(\OmegaUp\Time::get() - 60 * 60);
        $contestData = \OmegaUp\Test\Factories\Contest::createContest(
            new \OmegaUp\Test\Factories\ContestParams([
                'startTime' => $startTime,
                'finishTime' => $finishTime,
                'admissionMode' => 'public',
            ])
        );

        $problems = \OmegaUp\Test\Factories\Contest::insertProblemsInContest(
            $contestData
        );
        // One more problem, but in this case, it is private
        $login = self::login($contestData['director']);
        $problemData = \OmegaUp\Test\Factories\Problem::createProblem(
            new \OmegaUp\Test\Factories\ProblemParams([
                'visibility' => 'private',
            ]),
            $login
        );
        \OmegaUp\Test\Factories\Contest::addProblemToContest(
            $problemData,
            $contestData
        );

        ['identity' => $identity] = \OmegaUp\Test\Factories\User::createUser();
        $userLogin = self::login($identity);
        $contestDetails = \OmegaUp\Controllers\Contest::getContestPracticeDetailsForTypeScript(
            new \OmegaUp\Request([
                'auth_token' => $userLogin->auth_token,
                'contest_alias' => $contestData['request']['alias'],
            ])
        )['smartyProperties']['payload'];

        // Users should be able to see all the problems
        foreach ($contestDetails['problems'] as $problem) {
            $problemDetails = \OmegaUp\Controllers\Problem::apiDetails(
                new \OmegaUp\Request([
                    'auth_token' => $userLogin->auth_token,
                    'problem_alias' => $problem['alias'],
                    'prevent_problemset_open' => false,
                    'contest_alias' => $contestData['request']['alias'],
                ])
            );
            $this->assertEquals($problemDetails['alias'], $problem['alias']);
        }

        // But they are not included in the original contest scoreboard
        $response = \OmegaUp\Controllers\Problemset::apiScoreboard(
            new \OmegaUp\Request([
                'auth_token' => $userLogin->auth_token,
                'problemset_id' => $contestData['contest']->problemset_id,
            ])
        );
        $this->assertEmpty($response['ranking']);

        // Users can create runs
        $runData = \OmegaUp\Test\Factories\Run::createRun(
            $problemData,
            $contestData,
            $identity,
            /*$inPracticeMode=*/ true
        );

        // Grade the run
        \OmegaUp\Test\Factories\Run::gradeRun($runData);

        $userLogin = self::login($identity);
        $problemDetails = \OmegaUp\Controllers\Problem::apiDetails(
            new \OmegaUp\Request([
                'auth_token' => $userLogin->auth_token,
                'problem_alias' => $problemData['request']['problem_alias'],
                'prevent_problemset_open' => false,
                'contest_alias' => $contestData['request']['alias'],
            ])
        );

        $this->assertCount(1, $problemDetails['runs']);
    }

    public function testPrivateContestPracticeForNonRegisteredUsers() {
        // Get a contest in the past
        $startTime =  new \OmegaUp\Timestamp(\OmegaUp\Time::get() - 120 * 60);
        $finishTime =  new \OmegaUp\Timestamp(\OmegaUp\Time::get() - 60 * 60);
        $contestData = \OmegaUp\Test\Factories\Contest::createContest(
            new \OmegaUp\Test\Factories\ContestParams([
                'startTime' => $startTime,
                'finishTime' => $finishTime,
                'admissionMode' => 'private',
            ])
        );

        // Non-registered users can't access private contests, even in practice
        // mode
        [
            'identity' => $nonRegisteredIdentity,
        ] = \OmegaUp\Test\Factories\User::createUser();
        $userLogin = self::login($nonRegisteredIdentity);
        try {
            \OmegaUp\Controllers\Contest::getContestPracticeDetailsForTypeScript(
                new \OmegaUp\Request([
                    'auth_token' => $userLogin->auth_token,
                    'contest_alias' => $contestData['request']['alias'],
                ])
            );
            $this->fail(
                'User should not have access to contest in practice mode when it is private'
            );
        } catch (\OmegaUp\Exceptions\ForbiddenAccessException $e) {
            $this->assertEquals('userNotAllowed', $e->getMessage());
        }
    }

    public function testContestPracticeWhenOriginalContestHasNotEnded() {
        // Get a contest
        $contestData = \OmegaUp\Test\Factories\Contest::createContest();

        ['identity' => $identity] = \OmegaUp\Test\Factories\User::createUser();

        // Add user to our private contest
        \OmegaUp\Test\Factories\Contest::addUser($contestData, $identity);

        $userLogin = self::login($identity);
        $this->assertTrue(
            \OmegaUp\Controllers\Contest::shouldShowIntro(
                $identity,
                $contestData['contest']
            )
        );
        try {
            \OmegaUp\Controllers\Contest::getContestPracticeDetailsForTypeScript(
                new \OmegaUp\Request([
                    'auth_token' => $userLogin->auth_token,
                    'contest_alias' => $contestData['request']['alias'],
                ])
            );
        } catch (\OmegaUp\Exceptions\ForbiddenAccessException $e) {
            $this->assertEquals('originalContestHasNotEnded', $e->getMessage());
        }
    }

    private static function numberOfUsersSharingBasicInformation(
        array $contestants
    ): int {
        $numberOfContestants = 0;
        foreach ($contestants as $contestant) {
            if ($contestant['email']) {
                $numberOfContestants++;
            }
        }
        return $numberOfContestants;
    }
}
