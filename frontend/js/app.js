const API = '../backend/api.php';


/* =========================
   COMUNICAÇÃO COM A API
========================= */

async function api(action, options = {}) {

    const response = await fetch(`${API}?action=${action}`, {
        credentials: 'same-origin',

        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },

        ...options
    });

    // Primeiro pegamos como texto.
    // Assim conseguimos descobrir se o PHP
    // devolveu JSON ou uma página de erro.
    const texto = await response.text();

    let data;

    try {
        data = JSON.parse(texto);
    } catch (erro) {

        console.error('Resposta recebida pelo PHP:');
        console.error(texto);

        throw new Error(
            'O servidor não retornou um JSON válido. Verifique o PHP e o banco de dados.'
        );
    }

    if (!response.ok) {
        throw new Error(
            data.erro || 'Erro inesperado no servidor.'
        );
    }

    return data;
}


/* =========================
   FUNÇÕES AUXILIARES
========================= */

function qs(id) {
    return document.getElementById(id);
}


function esc(valor) {

    return String(valor || '').replace(
        /[&<>"']/g,
        function (caractere) {

            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[caractere];
        }
    );
}


function setMsg(id, texto, mostrar = true) {

    const elemento = qs(id);

    if (!elemento) {
        return;
    }

    elemento.textContent = texto;

    elemento.style.display =
        mostrar ? 'block' : 'none';
}


/* =========================
   USUÁRIO
========================= */

async function requireUser() {

    try {

        const dados = await api('me');

        if (!dados.usuario) {

            window.location.href = 'login.html';

            return null;
        }

        return dados.usuario;

    } catch (erro) {

        console.error(erro);

        window.location.href = 'login.html';

        return null;
    }
}


async function logout() {

    try {

        await api('logout', {
            method: 'POST'
        });

    } catch (erro) {

        console.error(erro);
    }

    window.location.href = 'index.html';
}


/* =========================
   CABEÇALHO
========================= */

function header() {

    const elemento = qs('header');

    if (!elemento) {
        return;
    }

    elemento.innerHTML = `
        <div class="topbar">

            <a class="logo" href="dashboard.html">
                BRIO
                <span>foco • constância</span>
            </a>

            <nav class="nav">

                <a href="dashboard.html">
                    Início
                </a>

                <a href="conteudos.html">
                    Conteúdos
                </a>

                <a href="simulados.html">
                    Simulados
                </a>

                <a href="redacoes.html">
                    Redações
                </a>

                <a href="calendario.html">
                    Calendário
                </a>

                <a href="ranking.html">
                    Ranking
                </a>

                <a href="planos.html">
                    Planos
                </a>

                <a href="perfil.html">
                    Perfil
                </a>

                <button onclick="logout()">
                    Sair
                </button>

            </nav>

        </div>
    `;
}


/* =========================
   DASHBOARD
========================= */

async function initDashboard() {

    const user = await requireUser();

    if (!user) {
        return;
    }

    header();

    qs('welcome').textContent =
        'Olá, ' + user.nome.split(' ')[0] + '!';

    const dados =
        await api('dashboard');

    qs('xp').textContent =
        dados.xp;

    qs('nivel').textContent =
        dados.nivel;

    qs('simulados').textContent =
        dados.simulados;

    qs('redacoes').textContent =
        dados.redacoes;


    if (dados.tarefas.length > 0) {

        qs('tarefas').innerHTML =
            dados.tarefas.map(function (tarefa) {

                return `
                    <div class="card">

                        <b>
                            ${esc(tarefa.titulo)}
                        </b>

                        <div class="muted small">
                            ${esc(tarefa.data)}
                            ${tarefa.horario
                                ? esc(tarefa.horario)
                                : ''}
                        </div>

                        <span class="badge">
                            ${esc(tarefa.status)}
                        </span>

                        ${
                            tarefa.status === 'pendente'
                                ? `
                                    <button
                                        class="btn green small"
                                        onclick="concluir(${tarefa.id})"
                                    >
                                        Concluir
                                    </button>
                                `
                                : ''
                        }

                    </div>
                `;

            }).join('');

    } else {

        qs('tarefas').innerHTML =
            '<div class="empty">Nenhuma atividade cadastrada.</div>';
    }
}


async function concluir(id) {

    await api('concluir_tarefa', {

        method: 'POST',

        body: JSON.stringify({
            id: id
        })
    });

    window.location.reload();
}


/* =========================
   CONTEÚDOS
========================= */

async function initConteudos() {

    const user = await requireUser();

    if (!user) {
        return;
    }

    header();

    const dados =
        await api('conteudos');


    if (dados.length > 0) {

        qs('lista').innerHTML =
            dados.map(function (conteudo) {

                return `
                    <article class="card">

                        <span class="badge">
                            ${esc(conteudo.materia)}
                        </span>

                        <h3>
                            ${esc(conteudo.titulo)}
                        </h3>

                        <p>
                            ${esc(conteudo.descricao)}
                        </p>

                        <p class="small">
                            ${esc(conteudo.texto)}
                        </p>

                        <div class="progress">
                            <span
                                style="width:${conteudo.percentual}%"
                            ></span>
                        </div>

                        <p class="small">
                            Progresso:
                            ${conteudo.percentual}%
                        </p>

                        <button
                            class="btn"
                            onclick="marcarConteudo(
                                ${conteudo.id},
                                ${conteudo.percentual >= 100 ? 0 : 100}
                            )"
                        >
                            ${
                                conteudo.percentual >= 100
                                    ? 'Refazer'
                                    : 'Concluir conteúdo'
                            }
                        </button>

                    </article>
                `;

            }).join('');

    } else {

        qs('lista').innerHTML =
            '<div class="empty">Nenhum conteúdo cadastrado.</div>';
    }
}


async function marcarConteudo(id, percentual) {

    await api('progresso', {

        method: 'POST',

        body: JSON.stringify({
            conteudo_id: id,
            percentual: percentual
        })
    });

    window.location.reload();
}


/* =========================
   SIMULADOS
========================= */

async function initSimulados() {

    const user = await requireUser();

    if (!user) {
        return;
    }

    header();

    const dados =
        await api('simulados');


    if (dados.length > 0) {

        qs('lista').innerHTML =
            dados.map(function (simulado) {

                return `
                    <article class="card">

                        <span class="badge">
                            ${esc(simulado.materia)}
                            •
                            ${esc(simulado.nivel)}
                        </span>

                        <h3>
                            ${esc(simulado.titulo)}
                        </h3>

                        <p>
                            ${esc(simulado.descricao)}
                        </p>

                        <p>
                            ${simulado.questoes}
                            questões
                        </p>

                        <a
                            class="btn"
                            href="simulado.html?id=${simulado.id}"
                        >
                            Iniciar
                        </a>

                    </article>
                `;

            }).join('');

    } else {

        qs('lista').innerHTML =
            '<div class="empty">Nenhum simulado cadastrado.</div>';
    }
}


let simuladoAtual = null;


async function initSimulado() {

    const user = await requireUser();

    if (!user) {
        return;
    }

    header();

    const id =
        new URLSearchParams(window.location.search)
        .get('id');


    if (!id) {

        window.location.href =
            'simulados.html';

        return;
    }


    simuladoAtual =
        await api('simulado&id=' + id);


    qs('titulo').textContent =
        simuladoAtual.titulo;

    qs('descricao').textContent =
        simuladoAtual.materia +
        ' • ' +
        simuladoAtual.nivel;


    qs('questoes').innerHTML =
        simuladoAtual.questoes.map(
            function (questao, indice) {

                let alternativas = '';

                const letras = [
                    'a',
                    'b',
                    'c',
                    'd',
                    'e'
                ];


                letras.forEach(function (letra) {

                    alternativas += `
                        <label class="option">

                            <input
                                type="radio"
                                name="q${questao.id}"
                                value="${letra.toUpperCase()}"
                            >

                            ${letra.toUpperCase()})
                            ${esc(
                                questao[
                                    'alternativa_' + letra
                                ]
                            )}

                        </label>
                    `;

                });


                return `
                    <div class="card question">

                        <b>
                            Questão ${indice + 1}
                        </b>

                        <p>
                            ${esc(questao.enunciado)}
                        </p>

                        ${alternativas}

                    </div>
                `;

            }
        ).join('');
}


async function finalizarSimulado() {

    if (!simuladoAtual) {
        return;
    }


    const respostas = {};


    simuladoAtual.questoes.forEach(
        function (questao) {

            const marcada =
                document.querySelector(
                    'input[name="q' +
                    questao.id +
                    '"]:checked'
                );


            if (marcada) {

                respostas[questao.id] =
                    marcada.value;

            } else {

                respostas[questao.id] =
                    null;
            }
        }
    );


    const dados =
        await api('finalizar_simulado', {

            method: 'POST',

            body: JSON.stringify({

                simulado_id:
                    simuladoAtual.id,

                respostas:
                    respostas
            })
        });


    window.location.href =
        'resultado.html?tentativa=' +
        dados.tentativa_id;
}


/* =========================
   RESULTADO
========================= */

async function initResultado() {

    const user = await requireUser();

    if (!user) {
        return;
    }

    header();


    const id =
        new URLSearchParams(window.location.search)
        .get('tentativa');


    const dados =
        await api(
            'gabarito&tentativa_id=' + id
        );


    qs('resultado').innerHTML = `

        <div class="card">

            <h2>
                ${esc(dados.titulo)}
            </h2>

            <div class="stats">

                <div class="card">
                    <span>Acertos</span>
                    <strong>
                        ${dados.acertos}
                    </strong>
                </div>

                <div class="card">
                    <span>Erros</span>
                    <strong>
                        ${dados.erros}
                    </strong>
                </div>

                <div class="card">
                    <span>Aproveitamento</span>
                    <strong>
                        ${dados.percentual}%
                    </strong>
                </div>

                <div class="card">
                    <span>XP</span>
                    <strong>
                        +${dados.xp_ganho}
                    </strong>
                </div>

            </div>

        </div>
    `;


    qs('gabarito').innerHTML =
        dados.questoes.map(
            function (questao, indice) {

                return `
                    <div class="card">

                        <b>
                            Questão ${indice + 1}
                        </b>

                        <p>
                            ${esc(questao.enunciado)}
                        </p>

                        <p>
                            Sua resposta:
                            <b>
                                ${esc(
                                    questao.resposta ||
                                    'Não respondida'
                                )}
                            </b>
                        </p>

                        <p>
                            Resposta correta:
                            <b>
                                ${esc(
                                    questao.resposta_correta
                                )}
                            </b>
                        </p>

                        <p class="muted">
                            ${esc(
                                questao.comentario
                            )}
                        </p>

                    </div>
                `;

            }
        ).join('');
}


/* =========================
   REDAÇÕES
========================= */

async function initRedacoes() {

    const user = await requireUser();

    if (!user) {
        return;
    }

    header();


    const resultado =
        await Promise.all([
            api('temas_redacao'),
            api('minhas_redacoes')
        ]);


    const temas =
        resultado[0];

    const minhas =
        resultado[1];


    qs('temas').innerHTML =
        temas.map(function (tema) {

            return `
                <article class="card">

                    <span class="badge">
                        Tema ${tema.id}
                    </span>

                    <h3>
                        ${esc(tema.titulo)}
                    </h3>

                    <p>
                        ${esc(tema.proposta)}
                    </p>

                    <p class="small muted">
                        ${esc(
                            tema.orientacoes || ''
                        )}
                    </p>

                    <a
                        class="btn"
                        href="redacao.html?id=${tema.id}"
                    >
                        Escrever
                    </a>

                </article>
            `;

        }).join('');


    if (minhas.length > 0) {

        qs('minhas').innerHTML =
            minhas.map(function (redacao) {

                let resultadoCorrecao = '';


                if (redacao.nota !== null) {

                    resultadoCorrecao = `
                        <p>
                            <b>
                                Nota: ${redacao.nota}
                            </b>
                        </p>

                        <p>
                            ${esc(
                                redacao.comentario
                            )}
                        </p>
                    `;

                } else {

                    resultadoCorrecao = `
                        <p class="muted">
                            Aguardando correção do professor.
                        </p>
                    `;
                }


                return `
                    <div class="card">

                        <h3>
                            ${esc(redacao.tema)}
                        </h3>

                        <span class="badge">
                            ${esc(redacao.status)}
                        </span>

                        ${resultadoCorrecao}

                    </div>
                `;

            }).join('');

    } else {

        qs('minhas').innerHTML =
            '<div class="empty">Você ainda não enviou redações.</div>';
    }
}


async function initRedacao() {

    const user = await requireUser();

    if (!user) {
        return;
    }

    header();


    const id =
        new URLSearchParams(window.location.search)
        .get('id');


    const temas =
        await api('temas_redacao');


    let temaEncontrado = null;


    temas.forEach(function (tema) {

        if (String(tema.id) === String(id)) {
            temaEncontrado = tema;
        }

    });


    if (!temaEncontrado) {

        window.location.href =
            'redacoes.html';

        return;
    }


    qs('tema').textContent =
        temaEncontrado.titulo;

    qs('proposta').textContent =
        temaEncontrado.proposta;

    qs('orientacoes').textContent =
        temaEncontrado.orientacoes || '';


    qs('enviar').onclick =
        async function () {

            try {

                await api('enviar_redacao', {

                    method: 'POST',

                    body: JSON.stringify({

                        tema_id: id,

                        texto:
                            qs('texto').value
                    })
                });


                alert(
                    'Redação enviada para correção!'
                );


                window.location.href =
                    'redacoes.html';

            } catch (erro) {

                setMsg(
                    'msg',
                    erro.message
                );
            }
        };
}


/* =========================
   RANKING
========================= */

async function initRanking() {

    const user = await requireUser();

    if (!user) {
        return;
    }

    header();


    const dados =
        await api('ranking');


    qs('lista').innerHTML = `

        <table class="table">

            <thead>

                <tr>
                    <th>#</th>
                    <th>Aluno</th>
                    <th>XP</th>
                    <th>Nível</th>
                </tr>

            </thead>

            <tbody>

                ${
                    dados.map(function (aluno) {

                        return `
                            <tr>

                                <td>
                                    ${aluno.posicao}º
                                </td>

                                <td>
                                    ${esc(aluno.nome)}
                                </td>

                                <td>
                                    ${aluno.xp}
                                </td>

                                <td>
                                    ${aluno.nivel}
                                </td>

                            </tr>
                        `;

                    }).join('')
                }

            </tbody>

        </table>
    `;
}


/* =========================
   PLANOS
========================= */

async function initPlanos() {

    const user = await requireUser();

    if (!user) {
        return;
    }

    header();


    const dados =
        await api('planos');


    qs('lista').innerHTML =
        dados.map(function (plano) {

            const valor =
                Number(
                    plano.valor_mensal
                )
                .toFixed(2)
                .replace('.', ',');


            return `
                <article class="card">

                    <h2>
                        ${esc(plano.nome)}
                    </h2>

                    <div class="price">

                        R$ ${valor}

                        <small>
                            /mês
                        </small>

                    </div>

                    <p>
                        ${esc(plano.descricao)}
                    </p>

                    <p>
                        ${plano.periodo_meses}
                        mês(es)
                    </p>

                    <button
                        class="btn"
                        onclick="assinar(${plano.id})"
                    >
                        Escolher plano
                    </button>

                </article>
            `;

        }).join('');
}


async function assinar(id) {

    const dados =
        await api('assinar', {

            method: 'POST',

            body: JSON.stringify({
                plano_id: id
            })
        });


    alert(dados.mensagem);

    window.location.reload();
}


/* =========================
   PERFIL
========================= */

async function initPerfil() {

    const user = await requireUser();

    if (!user) {
        return;
    }

    header();


    const dados =
        await api('perfil');


    qs('perfil').innerHTML = `

        <div class="card">

            <h2>
                ${esc(dados.nome)}
            </h2>

            <p>
                <b>E-mail:</b>
                ${esc(dados.email)}
            </p>

            <p>
                <b>Objetivo:</b>
                ${esc(
                    dados.objetivo ||
                    'Não informado'
                )}
            </p>

            <p>
                <b>Tipo:</b>
                ${esc(dados.tipo)}
            </p>

            <p>
                <b>XP:</b>
                ${dados.xp}
            </p>

            <p>
                <b>Nível:</b>
                ${dados.nivel}
            </p>

            <p>
                <b>Plano:</b>
                ${esc(
                    dados.plano ||
                    'Gratuito'
                )}
            </p>

        </div>
    `;
}


/* =========================
   CALENDÁRIO
========================= */

async function initCalendario() {

    const user = await requireUser();

    if (!user) {
        return;
    }

    header();


    const tarefas =
        await api('tarefas');


    if (tarefas.length > 0) {

        qs('tarefas').innerHTML =
            tarefas.map(function (tarefa) {

                return `
                    <div class="card">

                        <b>
                            ${esc(tarefa.titulo)}
                        </b>

                        <p class="small">

                            ${esc(tarefa.data)}

                            ${
                                tarefa.horario
                                    ? esc(tarefa.horario)
                                    : ''
                            }

                        </p>

                        <span class="badge">

                            ${esc(tarefa.tipo)}
                            •
                            ${esc(tarefa.status)}

                        </span>

                    </div>
                `;

            }).join('');

    } else {

        qs('tarefas').innerHTML =
            '<div class="empty">Nenhuma atividade. Use o formulário para criar.</div>';
    }


    qs('salvar').onclick =
        async function () {

            await api('tarefas', {

                method: 'POST',

                body: JSON.stringify({

                    titulo:
                        qs('titulo').value,

                    descricao:
                        qs('descricao').value,

                    data:
                        qs('data').value,

                    horario:
                        qs('horario').value,

                    duracao:
                        qs('duracao').value,

                    tipo:
                        qs('tipo').value
                })
            });


            window.location.reload();
        };
}


/* =========================
   PROFESSOR
========================= */

async function initProfessor() {

    const user =
        await requireUser();


    if (!user) {
        return;
    }


    if (user.tipo !== 'professor') {

        window.location.href =
            'dashboard.html';

        return;
    }


    header();


    const dados =
        await api('redacoes_professor');


    if (dados.length > 0) {

        qs('lista').innerHTML =
            dados.map(function (redacao) {

                return `
                    <article class="card">

                        <span class="badge">
                            ${esc(redacao.status)}
                        </span>

                        <h3>
                            ${esc(redacao.tema)}
                        </h3>

                        <p>
                            <b>Aluno:</b>
                            ${esc(redacao.aluno)}
                        </p>

                        <p class="small">
                            ${esc(redacao.texto)}
                        </p>

                        <div class="two">

                            <div class="field">

                                <label>
                                    Nota
                                </label>

                                <input
                                    id="nota${redacao.id}"
                                    type="number"
                                    min="0"
                                    max="1000"
                                    value="${
                                        redacao.nota === null
                                            ? ''
                                            : redacao.nota
                                    }"
                                >

                            </div>

                            <div class="field">

                                <label>
                                    Comentário
                                </label>

                                <textarea
                                    id="coment${redacao.id}"
                                    style="min-height:120px"
                                >${
                                    esc(
                                        redacao.comentario || ''
                                    )
                                }</textarea>

                            </div>

                        </div>

                        <button
                            class="btn green"
                            onclick="corrigir(${redacao.id})"
                        >
                            Salvar correção
                        </button>

                    </article>
                `;

            }).join('');

    } else {

        qs('lista').innerHTML =
            '<div class="empty">Nenhuma redação enviada.</div>';
    }
}


async function corrigir(id) {

    await api('corrigir_redacao', {

        method: 'POST',

        body: JSON.stringify({

            redacao_id:
                id,

            nota:
                qs('nota' + id).value,

            comentario:
                qs('coment' + id).value
        })
    });


    alert('Correção salva!');

    window.location.reload();
}