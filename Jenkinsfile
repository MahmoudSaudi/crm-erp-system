pipeline {
    agent any

    environment {
        IMAGE_NAME = "crm-erp"
        IMAGE_TAG  = "${env.BUILD_NUMBER}"
        DOCKER_HUB   = "mahmoudsaudi3082"
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
            }
        }
	
        stage('Push to Docker Hub') {
            steps {
                echo '>>> رفع الصورة لـ Docker Hub...'
                withCredentials([usernamePassword(
                    credentialsId: 'dockerhub-creds',
                    usernameVariable: 'DOCKER_USER',
                    passwordVariable: 'DOCKER_PASS'
                )]) {
                    sh """
                        echo "\$DOCKER_PASS" | docker login -u "\$DOCKER_USER" --password-stdin
                        docker push ${DOCKER_HUB}/${IMAGE_NAME}:${IMAGE_TAG}
                        docker push ${DOCKER_HUB}/${IMAGE_NAME}:latest
                    """
                }
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
