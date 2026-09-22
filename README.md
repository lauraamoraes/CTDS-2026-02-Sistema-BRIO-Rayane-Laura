# BRIO — Plataforma de Estudos

Projeto-base de TCC desenvolvido com:
- Front-end: HTML, CSS e JavaScript
- Back-end: PHP 8+
- Banco: MySQL/MariaDB
- Comunicação: API REST simples em PHP

## Módulos
- Cadastro e login
- Dashboard
- Conteúdos e progresso
- Simulados, questões, tentativas e gabarito comentado
- Redações por tema
- Área do professor para correção
- Calendário/rotina
- Gamificação: XP, níveis e conquistas
- Ranking
- Planos e assinatura simulada
- Perfil

## Instalação local (XAMPP)
1. Coloque a pasta `BRIO` dentro de `htdocs`.
2. Crie um banco MySQL chamado `brio`.
3. Importe `database/brio.sql`.
4. Abra `backend/config/database.php` e confira usuário/senha do MySQL.
5. Acesse `http://localhost/BRIO/frontend/`.

## Usuários de teste
O SQL cria:
- Professor: professor@brio.com / 123456
- Aluno: aluno@brio.com / 123456

As senhas do SQL usam SHA-256 apenas para simplificar a demonstração escolar.
Em produção, use `password_hash()` e `password_verify()`.

## Observação
O pagamento é propositalmente simulado no TCC. A contratação grava a assinatura no banco, mas não movimenta dinheiro real.
