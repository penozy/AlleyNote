 <?php

    declare(strict_types=1);

    use Phinx\Seed\AbstractSeed;

    /**
     * 使用者測試資料 Seeder
     * 建立範例使用者資料以供開發和測試使用
     */
    class UsersSeeder extends AbstractSeed
    {
        /**
         * 執行資料填充
         */
        public function run(): void
        {
            // 清空現有資料
            $this->table('users')->truncate();

            // 產生範例使用者資料
            $users = $this->generateSampleUsers();

            // 插入資料
            $this->table('users')->insert($users)->saveData();
        }

        /**
         * 產生範例使用者資料
         */
        private function generateSampleUsers(): array
        {
            $now = new DateTime();

            return [
                // 管理員使用者
                [
                    'username' => 'admin',
                    'email' => 'admin@alleynote.dev',
                    'password_hash' => password_hash('admin123', PASSWORD_ARGON2ID, [
                        'memory_cost' => 65536, // 64MB
                        'time_cost' => 4,       // 4 iterations
                        'threads' => 3,         // 3 threads
                    ]),
                    'created_at' => $now->format('Y-m-d H:i:s'),
                    'updated_at' => $now->format('Y-m-d H:i:s')
                ],

                // 一般使用者 1
                [
                    'username' => 'john_doe',
                    'email' => 'john.doe@example.com',
                    'password_hash' => password_hash('password123', PASSWORD_ARGON2ID, [
                        'memory_cost' => 65536,
                        'time_cost' => 4,
                        'threads' => 3,
                    ]),
                    'created_at' => $now->sub(new DateInterval('P1D'))->format('Y-m-d H:i:s'),
                    'updated_at' => $now->sub(new DateInterval('P1D'))->format('Y-m-d H:i:s')
                ],

                // 一般使用者 2
                [
                    'username' => 'jane_smith',
                    'email' => 'jane.smith@example.com',
                    'password_hash' => password_hash('securepass456', PASSWORD_ARGON2ID, [
                        'memory_cost' => 65536,
                        'time_cost' => 4,
                        'threads' => 3,
                    ]),
                    'created_at' => $now->sub(new DateInterval('P2D'))->format('Y-m-d H:i:s'),
                    'updated_at' => $now->sub(new DateInterval('P2D'))->format('Y-m-d H:i:s')
                ],

                // 測試使用者 1
                [
                    'username' => 'test_user1',
                    'email' => 'test1@alleynote.dev',
                    'password_hash' => password_hash('testpass1', PASSWORD_ARGON2ID, [
                        'memory_cost' => 65536,
                        'time_cost' => 4,
                        'threads' => 3,
                    ]),
                    'created_at' => $now->sub(new DateInterval('P3D'))->format('Y-m-d H:i:s'),
                    'updated_at' => $now->sub(new DateInterval('P3D'))->format('Y-m-d H:i:s')
                ],

                // 測試使用者 2
                [
                    'username' => 'test_user2',
                    'email' => 'test2@alleynote.dev',
                    'password_hash' => password_hash('testpass2', PASSWORD_ARGON2ID, [
                        'memory_cost' => 65536,
                        'time_cost' => 4,
                        'threads' => 3,
                    ]),
                    'created_at' => $now->sub(new DateInterval('P4D'))->format('Y-m-d H:i:s'),
                    'updated_at' => $now->sub(new DateInterval('P4D'))->format('Y-m-d H:i:s')
                ],

                // 停用/測試用的使用者
                [
                    'username' => 'inactive_user',
                    'email' => 'inactive@example.com',
                    'password_hash' => password_hash('inactivepass', PASSWORD_ARGON2ID, [
                        'memory_cost' => 65536,
                        'time_cost' => 4,
                        'threads' => 3,
                    ]),
                    'created_at' => $now->sub(new DateInterval('P7D'))->format('Y-m-d H:i:s'),
                    'updated_at' => $now->sub(new DateInterval('P7D'))->format('Y-m-d H:i:s')
                ]
            ];
        }
    }
