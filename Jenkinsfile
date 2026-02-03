pipeline {
    agent any

    options {
        skipDefaultCheckout()
        timestamps()
    }

    stages {

        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Install Dependencies') {
            steps {
                sh '''
                    composer install \
                      --no-interaction \
                      --no-progress \
                      --prefer-dist
                '''
            }
        }

        stage('Larastan (Static Analysis)') {
            steps {
                sh '''
                    ./vendor/bin/phpstan analyse \
                      --memory-limit=1G
                '''
            }
        }

        stage('PHPUnit Tests') {
            steps {
                sh '''
                    ./vendor/bin/phpunit --testdox
                '''
            }
        }
    }

    post {
        success {
            echo '✅ CI passed: code is safe to merge'
        }

        failure {
            echo '❌ CI failed: merge must be blocked'
        }
    }
}
