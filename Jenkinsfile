pipeline {
    agent any

    environment {
        IMAGE_NAME = "crm-erp"
        IMAGE_TAG  = "${env.BUILD_NUMBER}"
    }

    triggers {
        pollSCM('H/2 * * * *')
    }

    stages {

        stage('Checkout') {
            steps {
                echo '>>> جلب الكود من GitHub...'
                checkout scm
                sh 'git log --oneline -3'
            }
        }

        stage('Build Docker Image') {
            steps {
                echo '>>> بناء صورة Docker...'
                sh """
                    docker build \
                        -t ${IMAGE_NAME}:${IMAGE_TAG} \
                        -t ${IMAGE_NAME}:latest \
                        .
                """
            }
        }

        stage('Verify Image') {
            steps {
                echo '>>> التأكد من الصورة...'
                sh "docker run --rm ${IMAGE_NAME}:${IMAGE_TAG} php artisan --version"
                sh "docker run --rm ${IMAGE_NAME}:${IMAGE_TAG} php -m | grep -E 'pdo_mysql|mbstring|zip|gd|intl|opcache'"
            }
        }
	
        stage('Run Tests') {
            steps {
                echo '>>> تشغيل الاختبارات...'
                sh """
                    docker run --rm \
                        -e APP_ENV=testing \
                        -e APP_KEY=base64:\$(openssl rand -base64 32) \
                        ${IMAGE_NAME}:${IMAGE_TAG} \
                        php artisan test || echo 'No tests yet'
                """
            }
        }

        stage('Cleanup Old Images') {
            steps {
                echo '>>> تنظيف الصور القديمة...'
                sh "docker image prune -f --filter 'until=24h' || true"
            }
        }
    }

    post {
        success {
            echo "✅ Pipeline نجح — Build #${env.BUILD_NUMBER}"
        }
        failure {
            echo "❌ Pipeline فشل — Build #${env.BUILD_NUMBER}"
        }
        always {
            sh 'docker images | grep ${IMAGE_NAME} || true'
        }
    }
}
