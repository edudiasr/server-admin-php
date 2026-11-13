<?php

declare(strict_types=1);

namespace Admin\Domain;

/** Regra de negócio violada. A mensagem vai direto pra tela, então é escrita pro usuário. */
final class DomainError extends \RuntimeException
{
}
