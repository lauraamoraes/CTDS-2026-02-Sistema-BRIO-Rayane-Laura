CREATE DATABASE IF NOT EXISTS brio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE brio;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS usuario_conquistas, conquistas, pontuacoes, respostas, tentativas,
correcoes, redacoes, temas_redacao, tarefas, progresso_conteudos, conteudos,
questoes, simulados, assinaturas, planos, materias, usuarios;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('aluno','professor') NOT NULL DEFAULT 'aluno',
    objetivo VARCHAR(120) NULL,
    plano_atual_id INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE materias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(80) NOT NULL UNIQUE
);

CREATE TABLE planos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    valor_mensal DECIMAL(10,2) NOT NULL,
    periodo_meses INT NOT NULL,
    descricao TEXT NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE assinaturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    plano_id INT NOT NULL,
    inicio DATE NOT NULL,
    fim DATE NULL,
    status ENUM('ativa','cancelada','expirada') NOT NULL DEFAULT 'ativa',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (plano_id) REFERENCES planos(id)
);

ALTER TABLE usuarios
    ADD CONSTRAINT fk_usuario_plano
    FOREIGN KEY (plano_atual_id) REFERENCES planos(id) ON DELETE SET NULL;

CREATE TABLE conteudos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    materia_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    texto LONGTEXT NOT NULL,
    nivel ENUM('iniciante','intermediario','avancado') NOT NULL DEFAULT 'iniciante',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (materia_id) REFERENCES materias(id)
);

CREATE TABLE progresso_conteudos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    conteudo_id INT NOT NULL,
    percentual TINYINT UNSIGNED NOT NULL DEFAULT 0,
    concluido TINYINT(1) NOT NULL DEFAULT 0,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_progresso (usuario_id, conteudo_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (conteudo_id) REFERENCES conteudos(id) ON DELETE CASCADE
);

CREATE TABLE simulados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    materia_id INT NULL,
    nivel ENUM('iniciante','intermediario','avancado') NOT NULL DEFAULT 'intermediario',
    descricao VARCHAR(255) NOT NULL,
    tipo ENUM('geral','materia') NOT NULL DEFAULT 'materia',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (materia_id) REFERENCES materias(id)
);

CREATE TABLE questoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    simulado_id INT NOT NULL,
    enunciado TEXT NOT NULL,
    alternativa_a VARCHAR(500) NOT NULL,
    alternativa_b VARCHAR(500) NOT NULL,
    alternativa_c VARCHAR(500) NOT NULL,
    alternativa_d VARCHAR(500) NOT NULL,
    alternativa_e VARCHAR(500) NOT NULL,
    resposta_correta CHAR(1) NOT NULL,
    comentario TEXT NOT NULL,
    FOREIGN KEY (simulado_id) REFERENCES simulados(id) ON DELETE CASCADE
);

CREATE TABLE tentativas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    simulado_id INT NOT NULL,
    acertos INT NOT NULL DEFAULT 0,
    erros INT NOT NULL DEFAULT 0,
    percentual DECIMAL(5,2) NOT NULL DEFAULT 0,
    xp_ganho INT NOT NULL DEFAULT 0,
    realizado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (simulado_id) REFERENCES simulados(id)
);

CREATE TABLE respostas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tentativa_id INT NOT NULL,
    questao_id INT NOT NULL,
    resposta CHAR(1) NULL,
    correta TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (tentativa_id) REFERENCES tentativas(id) ON DELETE CASCADE,
    FOREIGN KEY (questao_id) REFERENCES questoes(id)
);

CREATE TABLE temas_redacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(180) NOT NULL,
    proposta TEXT NOT NULL,
    orientacoes TEXT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE redacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tema_id INT NOT NULL,
    texto LONGTEXT NOT NULL,
    status ENUM('enviada','corrigida') NOT NULL DEFAULT 'enviada',
    data_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (tema_id) REFERENCES temas_redacao(id)
);

CREATE TABLE correcoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    redacao_id INT NOT NULL UNIQUE,
    professor_id INT NOT NULL,
    nota DECIMAL(5,2) NOT NULL,
    comentario TEXT NOT NULL,
    data_correcao DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (redacao_id) REFERENCES redacoes(id) ON DELETE CASCADE,
    FOREIGN KEY (professor_id) REFERENCES usuarios(id)
);

CREATE TABLE tarefas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descricao VARCHAR(255) NULL,
    data DATE NOT NULL,
    horario TIME NULL,
    duracao INT NULL,
    tipo ENUM('estudo','simulado','redacao','outro') NOT NULL DEFAULT 'estudo',
    status ENUM('pendente','concluida') NOT NULL DEFAULT 'pendente',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE pontuacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    quantidade INT NOT NULL,
    motivo VARCHAR(150) NOT NULL,
    data DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE conquistas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    xp INT NOT NULL DEFAULT 0
);

CREATE TABLE usuario_conquistas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    conquista_id INT NOT NULL,
    conquistada_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_conquista (usuario_id, conquista_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (conquista_id) REFERENCES conquistas(id)
);

-- Planos
INSERT INTO planos (nome, valor_mensal, periodo_meses, descricao) VALUES
('Mensal', 59.90, 1, 'Acesso mensal à experiência completa da BRIO.'),
('Semestral', 49.90, 6, 'Plano de 6 meses com valor mensal reduzido.'),
('Anual', 39.90, 12, 'Plano de 12 meses com o menor valor mensal.');

-- Matérias
INSERT INTO materias (nome) VALUES
('Português'), ('Matemática'), ('História'), ('Geografia'),
('Biologia'), ('Física'), ('Química'), ('Redação');

-- Conquistas
INSERT INTO conquistas (nome, descricao, xp) VALUES
('Primeiro passo', 'Concluiu sua primeira atividade.', 20),
('Primeiro simulado', 'Finalizou seu primeiro simulado.', 30),
('Mão na massa', 'Enviou sua primeira redação.', 25),
('Constância', 'Concluiu 5 atividades.', 50),
('Evolução', 'Alcançou 500 XP.', 100);

-- Conteúdos
INSERT INTO conteudos (materia_id, titulo, descricao, texto, nivel) VALUES
(1, 'Interpretação de texto', 'Estratégias para compreender textos.', 'Leia o texto buscando tema, ideia principal, argumentos e relação entre as informações. Identifique palavras-chave e observe o contexto antes de escolher uma alternativa.', 'iniciante'),
(2, 'Funções do 1º grau', 'Conceitos básicos de funções lineares.', 'Uma função do primeiro grau pode ser representada por f(x) = ax + b, com a diferente de zero. O coeficiente a indica a inclinação e b indica o ponto em que a reta cruza o eixo y.', 'intermediario'),
(3, 'Brasil República', 'Panorama introdutório do período republicano.', 'A República brasileira passou por diferentes fases políticas e sociais. Para estudar o período, organize os acontecimentos em uma linha do tempo e relacione causas e consequências.', 'intermediario'),
(4, 'Globalização', 'Conceitos e impactos da globalização.', 'Globalização é um processo de intensificação das relações econômicas, culturais e tecnológicas entre diferentes partes do mundo.', 'iniciante');

-- Simulados
-- Simulados
INSERT INTO simulados
(titulo, materia_id, nivel, descricao, tipo)
VALUES

('Simulado Geral ENEM 01', NULL, 'intermediario',
 'Simulado completo com 90 questões de todas as matérias.',
 'geral'),

('Simulado Geral ENEM 02', NULL, 'intermediario',
 'Simulado completo com 90 questões de todas as matérias.',
 'geral'),

('Simulado Geral ENEM 03', NULL, 'avancado',
 'Simulado completo com 90 questões de todas as matérias.',
 'geral'),

('Simulado de Português', 1, 'intermediario',
 '30 questões de Língua Portuguesa.',
 'materia'),

('Simulado de Matemática', 2, 'intermediario',
 '30 questões de Matemática.',
 'materia'),

('Simulado de História', 3, 'intermediario',
 '30 questões de História.',
 'materia'),

('Simulado de Geografia', 4, 'intermediario',
 '30 questões de Geografia.',
 'materia'),

('Simulado de Biologia', 5, 'intermediario',
 '30 questões de Biologia.',
 'materia'),

('Simulado de Física', 6, 'intermediario',
 '30 questões de Física.',
 'materia'),

('Simulado de Química', 7, 'intermediario',
 '30 questões de Química.',
 'materia');

INSERT INTO questoes
(simulado_id,enunciado,alternativa_a,alternativa_b,alternativa_c,alternativa_d,alternativa_e,resposta_correta,comentario)
VALUES
(1,'A soma dos ângulos internos de um pentágono é','600°','540°','720°','450°','360°','B','A soma é (5-2)·180=540°.'),
(1,'O efeito estufa natural é importante porque','ajuda a manter a temperatura terrestre compatível com a vida','elimina toda a radiação solar','resfria permanentemente a atmosfera','impede a existência de oceanos','ocorre apenas em cidades','A','Gases atmosféricos retêm parte da radiação infravermelha e ajudam a manter a temperatura.'),
(1,'Em “O projeto foi aprovado pelos estudantes”, a expressão “pelos estudantes” exerce função de','objeto direto.','sujeito simples.','predicativo.','agente da passiva.','adjunto adverbial.','D','Na voz passiva, indica quem pratica a ação.'),
(1,'Em “É necessário que todos participem”, a oração “que todos participem” funciona como','vocativo.','objeto direto de “participem”.','adjunto adverbial.','aposto.','sujeito da oração principal.','E','A oração exerce função de sujeito de “é necessário”.'),
(1,'Dois resistores de 6 Ω em paralelo têm resistência equivalente','6 Ω','3 Ω','18 Ω','1 Ω','12 Ω','B','Para dois resistores iguais em paralelo, Req=R/2=3 Ω.'),
(1,'A Lei Áurea, de 1888, determinou','o início da imigração japonesa','a abolição legal da escravidão no Brasil','a independência de Portugal','a criação da República','a instituição do voto feminino','B','A lei extinguiu juridicamente a escravidão no Império.'),
(1,'A radioatividade é um fenômeno relacionado','à dissolução de sais','à formação de soluções','à emissão espontânea de radiação por núcleos instáveis','à eletrização por atrito','à combustão de qualquer combustível','C','Núcleos instáveis podem emitir partículas ou radiação eletromagnética.'),
(1,'A migração pendular é','o deslocamento frequente entre local de residência e local de estudo ou trabalho','a migração sazonal agrícola obrigatória','a migração rural permanente','a saída de refugiados de uma guerra','a mudança definitiva de país','A','É um deslocamento cotidiano ou frequente, sem mudança definitiva de residência.'),
(1,'Catalisador é uma substância que','é sempre um reagente','aumenta obrigatoriamente a quantidade de produto final','altera a velocidade da reação sem ser consumida globalmente','muda o equilíbrio permanentemente','impede toda reação','C','Catalisadores oferecem caminho de menor energia de ativação.'),
(1,'O ciclo do nitrogênio envolve','exclusivamente formação de rochas','somente fotossíntese','transformações do nitrogênio entre atmosfera, solo e seres vivos','apenas respiração','apenas evaporação da água','C','Bactérias e outros processos transformam compostos nitrogenados no ambiente.'),
(1,'A corrente elétrica em um resistor de 10 Ω ligado a 20 V é','2 A','200 A','0,5 A','10 A','30 A','A','Pela lei de Ohm, I=V/R=20/10=2 A.'),
(1,'O processo de duplicação do DNA é chamado','difusão','fermentação','transcrição','tradução','replicação','E','Replicação produz cópias do DNA antes da divisão celular.'),
(1,'A matriz de transportes influencia','a rotação terrestre','custos, velocidade e distribuição territorial dos fluxos de mercadorias e pessoas','exclusivamente a densidade demográfica','apenas a produção agrícola','somente o clima','B','Diferentes modais possuem custos e capacidades distintos.'),
(1,'A Proclamação da República no Brasil ocorreu em','1822','1930','1889','1888','1964','C','A República foi proclamada em 15 de novembro de 1889.'),
(1,'A transcrição corresponde à síntese de','glicose a partir de proteína','DNA a partir de proteína','RNA a partir de uma sequência de DNA','proteína a partir de lipídio','ATP a partir de RNA','C','Na transcrição, uma fita de RNA é sintetizada a partir do DNA.'),
(1,'Se dois indivíduos Aa têm descendentes, a probabilidade de aa é','0%','75%','50%','100%','25%','E','No cruzamento Aa x Aa, aa corresponde a 1 em 4 possibilidades.'),
(1,'A equação 2x+8=20 tem solução','4','8','10','6','12','D','2x=12, então x=6.'),
(1,'O Renascimento cultural valorizou','o fim das artes','a rejeição de estudos científicos','o feudalismo como modelo político obrigatório','o isolamento cultural','referências da Antiguidade clássica e o humanismo','E','O Renascimento recuperou referências clássicas e valorizou o ser humano e a investigação.'),
(1,'A vírgula em “Maria, representante da turma, apresentou o projeto” isola','um vocativo obrigatório.','um aposto explicativo.','um objeto direto.','um predicativo do objeto.','uma oração subordinada causal.','B','“Representante da turma” explica quem é Maria e funciona como aposto.'),
(1,'Isótopos são átomos de um mesmo elemento com','cargas sempre diferentes','elementos diferentes','massas iguais sempre','mesmo número de nêutrons e prótons diferentes','mesmo número de prótons e diferente número de nêutrons','E','Isótopos possuem mesmo Z e diferente número de massa.'),
(1,'Na frase “Faz dois anos que ela se mudou”, o verbo “fazer” é','plural porque indica tempo.','impessoal e permanece no singular.','substituído obrigatoriamente por “haver”.','pessoal e concorda com “dois anos”.','facultativo quanto à concordância.','B','Quando indica tempo decorrido, “fazer” é impessoal.'),
(1,'A matriz energética brasileira possui participação relevante de','petróleo como única fonte','lenha apenas','carvão mineral exclusivamente','fontes renováveis, como hidrelétrica, biomassa e eólica','energia nuclear exclusivamente','D','O Brasil apresenta participação significativa de fontes renováveis.'),
(1,'Em “O relógio correu durante a prova”, o verbo “correu” contribui para','introduzir uma citação.','apresentar uma definição científica.','estabelecer uma comparação explícita.','criar uma representação figurada da passagem rápida do tempo.','indicar deslocamento físico do relógio.','D','O verbo é empregado metaforicamente.'),
(1,'Na reação HCl + NaOH → NaCl + H2O ocorre uma','decomposição nuclear','oxidação exclusiva','combustão','neutralização ácido-base','polimerização','D','Ácido e base reagem formando sal e água.'),
(1,'O peso de um corpo de 5 kg, adotando g=10 m/s², é','5 N','2 N','500 N','15 N','50 N','E','P=mg=5·10=50 N.'),
(1,'Densidade demográfica corresponde','à relação entre população e área','ao número de nascimentos por ano','ao número de cidades','à taxa de urbanização','à renda média','A','Densidade é população dividida pela área territorial.'),
(1,'A média aritmética de 6, 8, 10 e 16 é','10','11','8','9','12','A','A soma é 40 e há quatro valores; 40/4=10.'),
(1,'Em uma notícia, o título “Chuva intensa provoca alagamentos em três bairros” destaca principalmente','uma descrição ficcional.','um argumento filosófico.','a opinião pessoal do repórter.','um acontecimento e seu efeito imediato.','uma instrução ao leitor.','D','O título sintetiza um fato e sua consequência.'),
(1,'A Primeira Guerra Mundial ocorreu entre','1910 e 1912','1939 e 1945','1929 e 1933','1914 e 1918','1945 e 1950','D','O conflito começou em 1914 e terminou em 1918.'),
(1,'O som não se propaga no vácuo porque','possui massa nula','é sempre transversal','necessita de um meio material','é uma onda eletromagnética','tem velocidade infinita','C','O som é uma onda mecânica e necessita de matéria para se propagar.'),
(1,'A Constituição brasileira de 1988 ficou conhecida por ampliar','direitos civis, sociais e políticos','a escravidão','o poder das oligarquias coloniais','o voto censitário','a censura estatal','A','A Constituição de 1988 consolidou direitos e instituições democráticas.'),
(1,'A solução da inequação 3x>12 é','x≤4','x≥4','x>3','x>4','x<4','D','Dividindo por 3, obtém-se x>4.'),
(1,'Em “Os resultados, que foram divulgados ontem, surpreenderam a equipe”, as vírgulas isolam','uma oração subordinada adjetiva explicativa.','uma oração adjetiva restritiva.','um objeto indireto.','uma oração coordenada conclusiva.','um vocativo.','A','A oração acrescenta informação sobre “os resultados” e é isolada por vírgulas.'),
(1,'A solução de |x-4|=3 é','1 e 4','1 e 7','3 e 7','4 e 7','-1 e 7','B','x-4=3 ou x-4=-3.'),
(1,'Filtração é adequada para separar','dois sólidos solúveis','dois líquidos miscíveis','isótopos','sólido insolúvel de líquido','gases dissolvidos','D','A filtração usa uma barreira que retém o sólido insolúvel.'),
(1,'A redemocratização brasileira ganhou marco importante com','a Constituição de 1988','o AI-5','a Lei Áurea','a Carta de 1824','o Tratado de Tordesilhas','A','A Constituição de 1988 reorganizou o Estado sob bases democráticas.'),
(1,'As hemácias transportam oxigênio principalmente por meio da','bile','queratina','insulina','melanina','hemoglobina','E','A hemoglobina liga-se ao oxigênio no sangue.'),
(1,'A digestão de proteínas inicia-se, de forma significativa, no','reto','estômago','intestino grosso','esôfago','boca exclusivamente','B','No estômago, a pepsina atua sobre proteínas em meio ácido.'),
(1,'A mudança de “A diretora entregou os certificados aos alunos” para a voz passiva resulta em','A diretora foi entregue pelos certificados.','Os certificados foram entregues pela diretora aos alunos.','Os alunos entregaram os certificados à diretora.','Os certificados entregaram a diretora aos alunos.','Aos alunos entregou-se a diretora.','B','O objeto direto da ativa torna-se sujeito paciente na passiva.'),
(1,'O Tratado de Tordesilhas, de 1494, estabeleceu','o fim das monarquias ibéricas','a independência do Brasil','uma divisão de áreas de exploração entre Portugal e Espanha','o fim da escravidão atlântica','a criação da União Europeia','C','O acordo procurou delimitar áreas de expansão ultramarina de Portugal e Espanha.'),
(1,'A solubilidade de um sólido em água geralmente aumenta com a temperatura, mas','isso depende da substância e da natureza do processo','é sempre independente da temperatura','sempre diminui','nunca muda','é determinada apenas pela pressão','A','O efeito da temperatura varia conforme a substância.'),
(1,'A meiose é importante porque','produz apenas células diploides','reduz o número de cromossomos e gera variabilidade genética','ocorre apenas em bactérias','duplica indefinidamente células somáticas','elimina recombinação genética','B','A meiose forma gametas e contribui para a variabilidade.'),
(1,'A ligação em uma molécula de O2 é','metálica','covalente polar necessariamente','ponte de hidrogênio','iônica','covalente apolar','E','Átomos iguais compartilham elétrons com mesma eletronegatividade.'),
(1,'O valor de 2⁵·2² é','64','256','32','128','16','D','Potências de mesma base: 2^(5+2)=2⁷=128.'),
(1,'Pelo princípio de Arquimedes, o empuxo sobre um corpo imerso corresponde','ao peso do corpo sempre','à pressão atmosférica','ao peso do fluido deslocado','à massa do corpo','à energia cinética do fluido','C','O empuxo tem módulo igual ao peso do fluido deslocado.'),
(1,'A temperatura em escala Kelvin é obtida de Celsius, aproximadamente, por','K=°C-273','K=°C/273','K=°C+273','K=2°C','K=273/°C','C','A conversão aproximada soma 273 ao valor em Celsius.'),
(1,'A mediana do conjunto 3, 5, 7, 8, 12 é','12','5','8','6','7','E','Em cinco valores ordenados, a mediana é o terceiro.'),
(1,'A energia cinética de um corpo de 2 kg a 4 m/s é','4 J','12 J','8 J','32 J','16 J','E','Ec=mv²/2=2·16/2=16 J.'),
(1,'Uma urna tem 3 bolas vermelhas e 2 azuis. A probabilidade de retirar uma azul é','3/5','1/5','1/2','2/5','2/3','D','São 2 casos favoráveis em 5 possíveis.'),
(1,'A desertificação corresponde','à inundação permanente de solos','à degradação de terras em áreas secas, com perda de produtividade','à formação natural de todos os desertos','ao aumento da cobertura vegetal','à urbanização de áreas costeiras','B','É um processo de degradação ambiental associado sobretudo a regiões áridas, semiáridas e subúmidas secas.'),
(1,'As fontes eólica e solar são classificadas como','minerais metálicos','fósseis','não renováveis','renováveis','nucleares','D','Vento e radiação solar são recursos renováveis.'),
(1,'A concentração comum de 20 g de soluto em 2 L de solução é','2 g/L','22 g/L','10 g/L','40 g/L','0,1 g/L','C','C=m/V=20/2=10 g/L.'),
(1,'Em “Ele é um leão nos negócios”, a figura de linguagem predominante é','ironia obrigatória.','pleonasmo.','aliteração.','metáfora.','catacrese.','D','Há uma comparação implícita entre a pessoa e um leão.'),
(1,'Vírus são considerados acelulares porque','realizam metabolismo independente completo','são organismos pluricelulares','possuem duas células','não possuem estrutura celular própria','têm tecidos','D','Vírus não apresentam organização celular e dependem de células hospedeiras para replicação.'),
(1,'Se uma mercadoria passa de R$ 80 para R$ 100, o aumento percentual é','15%','10%','20%','30%','25%','E','O aumento é 20 sobre 80: 20/80=25%.'),
(1,'A ONU foi criada em 1945 com o objetivo de','substituir todos os governos nacionais','encerrar a expansão marítima portuguesa','controlar o comércio brasileiro','favorecer cooperação internacional e segurança coletiva','organizar a Revolução Industrial','D','A organização surgiu após a Segunda Guerra Mundial.'),
(1,'Polímeros são formados pela repetição de','unidades menores chamadas monômeros','íons isolados','moléculas de água apenas','átomos de um único elemento obrigatoriamente','metais puros','A','Polímeros são macromoléculas constituídas por unidades repetitivas.'),
(1,'O emprego de “portanto” normalmente introduz','uma condição.','uma exemplificação.','uma oposição.','uma conclusão.','uma explicação causal.','D','“Portanto” é conectivo conclusivo.'),
(1,'Uma PG tem primeiro termo 2 e razão 3. O 4º termo é','54','18','24','27','81','A','a4=2·3³=54.'),
(1,'Uma solução com pH 3 é','básica','neutra','sempre saturada','ácida','apolar','D','Valores de pH abaixo de 7 indicam meio ácido em condições usuais.'),
(1,'O volume de um cubo de aresta 4 cm é','32 cm³','64 cm³','16 cm³','12 cm³','48 cm³','B','V=4³=64.'),
(1,'Uma pirâmide etária com base estreita e topo relativamente mais largo indica','alta natalidade e população muito jovem','mortalidade infantil elevada obrigatoriamente','ausência de idosos','redução da natalidade e envelhecimento populacional','crescimento explosivo da população infantil','D','Base estreita representa menor participação de jovens; topo relativamente maior indica envelhecimento.'),
(1,'O planejamento urbano pode contribuir para mobilidade ao','reduzir transporte público','concentrar todos os serviços em um ponto','eliminar calçadas','priorizar apenas automóveis individuais','integrar transporte coletivo, uso do solo e infraestrutura','E','Planejamento integrado pode diminuir deslocamentos e melhorar a acessibilidade.'),
(1,'A escravidão africana no Brasil esteve ligada','à ausência de agricultura','ao trabalho exclusivamente livre','ao fim do tráfico no século XVI','à produção colonial e a redes comerciais atlânticas','à industrialização do século XX','D','O trabalho escravizado sustentou parte importante da economia colonial e imperial.'),
(1,'Bacia hidrográfica é','a área drenada por um rio principal e seus afluentes','apenas o leito de um rio','uma área sem drenagem','uma unidade climática','uma formação exclusivamente subterrânea','A','A bacia reúne o rio principal, afluentes e áreas de drenagem.'),
(1,'A mitose produz, em condições normais,','uma célula sem núcleo','duas células geneticamente semelhantes à célula original','quatro células haploides','quatro células diploides diferentes','duas células geneticamente sempre diferentes','B','A mitose mantém o número de cromossomos e gera duas células semelhantes.'),
(1,'A corrente elétrica é definida como','energia por unidade de massa','massa por volume','carga elétrica que atravessa uma seção por unidade de tempo','potência por distância','força por área','C','I=ΔQ/Δt.'),
(1,'O êxodo rural é','o deslocamento diário dentro da cidade','a migração da população do campo para as cidades','a migração entre países ricos','a migração de cidades para o campo','a saída de empresas do país','B','Êxodo rural designa o deslocamento do campo para áreas urbanas.'),
(1,'A unidade SI de frequência é','joule','newton','pascal','hertz','watt','D','Frequência é medida em hertz (Hz).'),
(1,'Uma onda com frequência 5 Hz tem período','0,05 s','0,2 s','5 s','10 s','2,5 s','B','T=1/f=1/5=0,2 s.'),
(1,'Assinale a alternativa em que a regência está de acordo com a norma-padrão.','Os estudantes assistiram pela palestra.','Os estudantes assistiram a palestra.','Os estudantes assistiram à palestra.','Os estudantes assistiram na palestra.','Os estudantes assistiram com a palestra.','C','No sentido de ver/presenciar, assistir rege a preposição “a”.'),
(1,'Se 4 trabalhadores fazem uma tarefa em 12 dias, mantendo o mesmo ritmo, 8 trabalhadores fariam a tarefa em','8 dias','24 dias','6 dias','10 dias','12 dias','C','É uma proporção inversa: dobrando trabalhadores, o tempo cai pela metade.'),
(1,'Segundo Le Chatelier, um sistema em equilíbrio tende a','mudar a constante de equilíbrio por qualquer alteração','responder a uma perturbação deslocando-se de modo a minimizá-la','eliminar todos os produtos','parar imediatamente','aumentar sempre a temperatura','B','O princípio descreve a resposta do sistema a perturbações.'),
(1,'A Revolução Industrial iniciada na Inglaterra no século XVIII foi marcada por','desaparecimento do trabalho assalariado','retorno ao artesanato doméstico','fim das cidades','mecanização da produção e uso crescente de máquinas','redução da produção em fábricas','D','A industrialização alterou técnicas produtivas, trabalho e urbanização.'),
(1,'A ambiguidade em um texto ocorre quando','há excesso de dados estatísticos.','o texto apresenta apenas uma informação.','o autor usa somente frases curtas.','uma construção permite mais de uma interpretação.','não existem pronomes.','D','Ambiguidade é a possibilidade de atribuir sentidos diferentes a uma expressão.'),
(1,'A molécula de RNA mensageiro participa','da produção de glicose no fígado','da digestão celular','do transporte de oxigênio','da síntese de proteínas levando informação do DNA aos ribossomos','da formação de ossos','D','O RNAm leva a sequência codificada para a tradução.'),
(1,'O clima equatorial apresenta, em geral','grande amplitude térmica diária obrigatória','neve permanente','baixa umidade e inverno rigoroso','temperaturas elevadas e alta umidade durante grande parte do ano','chuvas inexistentes','D','O clima equatorial é quente e úmido, com elevada precipitação.'),
(1,'O número de Avogadro é aproximadamente','1,60×10^-19','3,00×10^8','9,8','6,02×10^23','6,02×10^3','D','Representa o número de entidades em um mol.'),
(1,'A seleção natural atua sobre','características adquiridas que nunca são herdadas','indivíduos sem diferenças','somente mutações prejudiciais','variações herdáveis que influenciam a sobrevivência e reprodução','apenas organismos domesticados','D','A seleção altera frequências de características herdáveis ao longo das gerações.'),
(1,'O AI-5, de 1968, durante a ditadura militar brasileira, ampliou','participação popular no governo','direitos eleitorais diretos','autonomia sindical plena','instrumentos de repressão e restrição de direitos políticos','liberdade de imprensa','D','O ato endureceu o regime e restringiu direitos e garantias.'),
(1,'A expansão marítima portuguesa dos séculos XV e XVI buscou','eliminar as monarquias','criar a ONU','isolar a Europa do comércio','abandonar a navegação','novas rotas comerciais e acesso a mercados','E','Portugal procurou novas rotas para o comércio, especialmente com o Oriente.'),
(1,'Na pilha, uma reação espontânea de oxirredução pode gerar','somente luz visível','prótons livres no vácuo','apenas energia nuclear','energia elétrica','massa adicional','D','Pilhas convertem energia química em elétrica.'),
(1,'O Iluminismo defendia, entre outros princípios','valorização da razão e crítica ao absolutismo','retorno ao feudalismo','poder divino incontestável','fim da ciência','censura do pensamento científico','A','Pensadores iluministas enfatizaram razão, direitos e crítica às estruturas tradicionais.'),
(1,'O DNA tem como função principal','transportar oxigênio','formar apenas lipídios','produzir energia diretamente','digerir proteínas','armazenar informação genética','E','O DNA contém a informação hereditária.'),

-- Temas de redação
INSERT INTO temas_redacao (titulo, proposta, orientacoes) VALUES
('Os impactos da tecnologia na educação',
 'A tecnologia está cada vez mais presente no processo de aprendizagem. Discuta os impactos positivos e os desafios de seu uso na educação.',
 'Apresente uma tese clara, desenvolva argumentos e finalize com uma proposta de intervenção coerente.'),
('Desafios para manter uma rotina de estudos',
 'A falta de organização e as distrações podem dificultar a constância nos estudos. Discuta estratégias para enfrentar esse problema.',
 'Utilize argumentos relacionados à rotina, disciplina, ambiente de estudo e uso consciente da tecnologia.'),
('O papel da educação na transformação social',
 'Discuta como a educação pode contribuir para mudanças sociais e para a formação de cidadãos.',
 'Construa uma argumentação organizada e apresente uma conclusão relacionada ao problema discutido.');

-- Usuários de demonstração (senha = 123456 em SHA-256)
INSERT INTO usuarios (nome,email,senha,tipo,objetivo) VALUES
('Professor BRIO','professor@brio.com',SHA2('123456',256),'professor','Correção de redações'),
('Aluno BRIO','aluno@brio.com',SHA2('123456',256),'aluno','Vestibular');

SET @aluno = (SELECT id FROM usuarios WHERE email='aluno@brio.com');
SET @prof = (SELECT id FROM usuarios WHERE email='professor@brio.com');

INSERT INTO tarefas (usuario_id,titulo,descricao,data,horario,duracao,tipo)
VALUES
(@aluno,'Estudar interpretação de texto','Revisar conteúdo de Português.',CURDATE(),'14:00:00',50,'estudo'),
(@aluno,'Fazer simulado','Realizar um simulado de Português.',CURDATE(),'16:00:00',40,'simulado');

INSERT INTO progresso_conteudos (usuario_id,conteudo_id,percentual,concluido)
VALUES (@aluno,1,65,0);

INSERT INTO pontuacoes (usuario_id,quantidade,motivo) VALUES
(@aluno,120,'Bônus inicial de demonstração'),
(@aluno,40,'Atividade concluída');
