<?php
/*
Plugin Name: CSV Post Importer
Description: Importa posts de um arquivo CSV, incluindo tags e imagens de destaque. Desenvolvido por Jean.
Version: 1.3
Author: Jean
Author URI: mailto:jean.carlos@uios.com.br
Plugin URI: https://uios.com.br/wordpress/plugins/uios-all-import-free
License: MIT
License URI: https://opensource.org/licenses/MIT
*/

/*
MIT License

Copyright (c) 2024 Jean

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

// Função para adicionar a página de administração
function cpi_add_admin_menu() {
    add_menu_page('CSV Post Importer', 'CSV Post Importer', 'manage_options', 'csv-post-importer', 'cpi_admin_page');
}
add_action('admin_menu', 'cpi_add_admin_menu');

// Função para exibir a página de administração
function cpi_admin_page() {
    ?>
    <h1>Importar Posts via CSV</h1>
    <form enctype="multipart/form-data" method="post" action="">
        <input type="file" name="csv_file" />
        <input type="checkbox" name="debug" value="1"> Ativar Debug<br>
        <input type="submit" name="submit" value="Importar" />
    </form>
    <?php
    if (isset($_POST['submit'])) {
        cpi_import_csv();
    }
    cpi_display_import_history();
}

// Função para importar o CSV
function cpi_import_csv() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cpi_import_history';

    if (!empty($_FILES['csv_file']['tmp_name'])) {
        $csv_file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $header = fgetcsv($csv_file);
        $import_id = time();
        $total_rows = count(file($_FILES['csv_file']['tmp_name'])) - 1;
        $current_row = 0;

        echo '<div style="width: 100%; background-color: #f3f3f3; border: 1px solid #ccc; margin-top: 20px;">';
        echo '<div id="progress-bar" style="width: 0%; height: 20px; background-color: #4caf50;"></div>';
        echo '</div>';
        echo '<div style="height: 200px; overflow-y: scroll; border: 1px solid #ccc; margin-top: 10px; padding: 10px;">';

        while (($row = fgetcsv($csv_file)) !== FALSE) {
            $current_row++;
            $progress = ($current_row / $total_rows) * 100;
            echo '<script>document.getElementById("progress-bar").style.width = "' . $progress . '%";</script>';

            $post_data = array(
                'ID' => $row[0],
                'post_title' => $row[1],
                'post_content' => $row[2],
                'post_excerpt' => $row[3],
                'post_date' => $row[4],
                'post_type' => $row[5],
                'guid' => $row[6],
                'post_status' => $row[36],
                'post_author' => $row[37],
                'post_name' => $row[43],
                'post_parent' => $row[46],
                'menu_order' => $row[48],
                'comment_status' => $row[49],
                'ping_status' => $row[50],
                'post_modified' => $row[51]
            );

            $post_id = wp_insert_post($post_data);

            if (is_wp_error($post_id)) {
                $status = 'Erro: ' . $post_id->get_error_message();
            } else {
                $status = 'OK';
            }

            // Adicionar tags e categorias
            if (!empty($row[14])) {
                $categories = explode(',', $row[14]);
                wp_set_post_categories($post_id, $categories);
            }
            if (!empty($row[15])) {
                $tags = explode(',', $row[15]);
                wp_set_post_tags($post_id, $tags);
            }

            // Adicionar imagem de destaque
            if (!empty($row[7])) {
                $image_url = $row[7];
                $image_id = cpi_upload_image($image_url);
                if ($image_id) {
                    set_post_thumbnail($post_id, $image_id);
                }
            }

            // Salvar histórico de importação
            $wpdb->insert($table_name, array(
                'import_id' => $import_id,
                'post_id' => $post_id,
                'import_time' => current_time('mysql')
            ));

            // Debug
            if (isset($_POST['debug']) && $_POST['debug'] == 1) {
                echo '<p>ID: ' . $post_id . ' | Título: ' . $post_data['post_title'] . ' | Status: ' . $status . '</p>';
            }
        }
        fclose($csv_file);
        echo '</div>';
        echo '<p>Importação concluída!</p>';
    }
}

// Função para fazer upload da imagem
function cpi_upload_image($image_url) {
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($image_url);
    $filename = basename($image_url);
    if (wp_mkdir_p($upload_dir['path'])) {
        $file = $upload_dir['path'] . '/' . $filename;
    } else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }
    file_put_contents($file, $image_data);

    $wp_filetype = wp_check_filetype($filename, null);
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit'
    );
    $attach_id = wp_insert_attachment($attachment, $file);
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file);
    wp_update_attachment_metadata($attach_id, $attach_data);

    return $attach_id;
}

// Função para exibir histórico de importações
function cpi_display_import_history() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cpi_import_history';
    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY import_time DESC");

    if ($results) {
        echo '<h2>Histórico de Importações</h2>';
        echo '<table>';
        echo '<tr><th>ID da Importação</th><th>ID do Post</th><th>Data da Importação</th><th>Ações</th></tr>';
        foreach ($results as $row) {
            echo '<tr>';
            echo '<td>' . $row->import_id . '</td>';
            echo '<td>' . $row->post_id . '</td>';
            echo '<td>' . $row->import_time . '</td>';
            echo '<td><a href="?page=csv-post-importer&delete_import=' . $row->import_id . '">Excluir</a></td>';
            echo '</tr>';
        }
        echo '</table>';
    }
}

// Função para excluir importação
function cpi_delete_import($import_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cpi_import_history';
    $posts = $wpdb->get_results($wpdb->prepare("SELECT post_id FROM $table_name WHERE import_id = %d", $import_id));

    foreach ($posts as $post) {
        wp_delete_post($post->post_id, true);
    }

    $wpdb->delete($table_name, array('import_id' => $import_id));
}

// Hook para excluir importação
if (isset($_GET['delete_import'])) {
    cpi_delete_import($_GET['delete_import']);
}

// Função para criar tabela de histórico de importações
function cpi_create_import_history_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cpi_import_history';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        import_id bigint(20) NOT NULL,
        post_id bigint(20) NOT NULL,
        import_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'cpi_create_import_history_table');
?>