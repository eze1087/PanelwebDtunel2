<?php
// Inicializa a sessão para podermos modificar as variáveis de login
session_start();

// Verifica se foi passado um ID na URL e se existem contas salvas na sessão
if (isset($_GET['id']) && !empty($_SESSION['saved_accounts'])) {
    $targetId = (int)$_GET['id'];
    $accounts = $_SESSION['saved_accounts'];

    foreach ($accounts as $acc) {
        // Encontra a conta correspondente ao ID clicado
        if (isset($acc['id']) && $acc['id'] === $targetId) {
            
            // Aqui fazemos a troca! 
            // Substitua 'user_id' ou 'id' pelo nome exato da variável de sessão 
            // que o seu sistema DTunnel usa para identificar quem está logado.
            $_SESSION['user_id'] = $acc['id']; 
            
            // Opcional: Actualizar outras variáveis de sessão se seu sistema exigir
            if(isset($acc['email'])) $_SESSION['email'] = $acc['email'];
            if(isset($acc['name'])) $_SESSION['username'] = $acc['name'];
            if(isset($acc['role'])) $_SESSION['role'] = $acc['role'];

            // Troca realizada con éxito, encerra o loop
            break;
        }
    }
}

// Redireciona o usuário de volta para a Home para carregar os dados da nova conta
header("Location: /home");
exit;
?>