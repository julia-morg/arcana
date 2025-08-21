<?php

namespace App\Console\Commands;

use App\Telegram;
use Illuminate\Console\Command;
use Telegram\Bot\Api;

class SetCommands extends Command
{
	protected $signature = 'app:set-commands';

	protected $description = 'Обнаружить доступные команды бота и зарегистрировать их в Telegram';

	public function handle(): int
	{
		$token = config('app.tg_token');
		if (empty($token)) {
			$this->error('Не задан TELEGRAM_TOKEN в окружении.');
			return self::FAILURE;
		}

		try {
			$telegram = new Api($token);
			$bot = new Telegram();
			$map = $bot->getBotCommands();
			$commands = [];
			foreach ($map as $name => $meta) {
				if (in_array($name, ['help', 'menu_dice'], true)) {
					continue;
				}
				$description = (string) ($meta['description'] ?? '');
				if ($description === '') {
					continue;
				}
				$commands[] = [
					'command' => $name,
					'description' => $description,
				];
			}
			if (empty($commands)) {
				$this->warn('Команды не найдены.');
				return self::SUCCESS;
			}
			$telegram->setMyCommands(['commands' => $commands]);
			$this->info('Зарегистрировано команд: ' . count($commands));
			foreach ($commands as $c) {
				$this->line($c['command'] . ' - ' . $c['description']);
			}
			return self::SUCCESS;
		} catch (\Throwable $e) {
			$this->error('Ошибка регистрации команд: ' . $e->getMessage());
			return self::FAILURE;
		}
	}
}


